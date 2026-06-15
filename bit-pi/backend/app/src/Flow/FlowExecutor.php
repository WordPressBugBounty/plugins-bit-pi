<?php

namespace BitApps\Pi\src\Flow;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Helpers\DateTimeHelper;
use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Helpers\Node;
use BitApps\Pi\HTTP\Controllers\HookListenerController;
use BitApps\Pi\Model\Flow as FlowModel;
use BitApps\Pi\Model\FlowLog;
use BitApps\Pi\Services\FlowHistoryService;
use BitApps\Pi\Services\FlowService;
use BitApps\Pi\Services\LogService;
use BitApps\Pi\Services\NodeService;
use BitApps\Pi\src\Log\LogHandler;
use BitApps\Pi\src\Queue\BackgroundProcessHandler;
use BitApps\Pi\src\Tools\FlowToolsFactory;
use stdClass;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}


class FlowExecutor extends BackgroundProcessHandler
{
    protected $action = 'background_process_request';

    protected $prefix = Config::VAR_PREFIX;

    private $flowId;

    private $flowHistoryId;

    private $currentNode;

    private $listenerType;

    private $flowSettings;

    private $nodeExecutor;

    public function __construct()
    {
        parent::__construct();
        $this->nodeExecutor = new NodeExecutor();
    }

    /**
     * Flow Executor run.
     *
     * @param collection  $flow
     * @param mixed       $triggerData
     * @param mixed       $flowHistoryId
     * @param mixed       $executeType
     * @param null|string $nextNodId
     */
    public static function execute($flow, $triggerData = [], $flowHistoryId = null, $executeType = null, $nextNodId = null)
    {
        $flowExecutorInstance = new self();

        $flowExecutorInstance->checkAndCleanupProcessLock();

        $flowSettings = JSON::maybeDecode($flow->settings, true);

        $listenerType = $flow->listener_type;

        $currentDateTime = (new DateTimeHelper())->getCurrentDateTime();

        $captureStartTime = $flowSettings['capture_start_time'] ?? null;

        if ($captureStartTime && strtotime(HookListenerController::LISTENER_TIME_LIMIT . $flowSettings['capture_start_time']) < strtotime($currentDateTime)) {
            $updatedFlow = FlowService::captureStatusUpdate($flow->id, false);

            $listenerType = $updatedFlow->listener_type ?? null;
        }

        if ($listenerType === FlowModel::LISTENER_TYPE['CAPTURE']) {
            NodeService::saveNodeVariables($flow->id, $triggerData, $flow->map->id);
            FlowService::captureStatusUpdate($flow->id, FlowModel::IS_HOOK_CAPTURED);

            return;
        }

        if ($listenerType === FlowModel::LISTENER_TYPE['RUN_ONCE']) {
            FlowModel::findOne(['id' => $flow->id])->update(
                [
                    'listener_type'   => FlowModel::LISTENER_TYPE['NONE'],
                    'is_hook_capture' => FlowModel::IS_HOOK_CAPTURED
                ]
            )->save();
        }

        if (
            $listenerType === FlowModel::LISTENER_TYPE['RUN_ONCE']
            || $flow->is_active === 1
        ) {
            $parentFlowHistoryId = null;

            if ($executeType === 're-execute') {
                $parentFlowHistoryId = $flowHistoryId;
                $flowHistoryId = null;
            }
            $flowHistoryId = FlowHistoryService::createHistoryWithTriggerNode(
                $flow->id,
                $flowHistoryId,
                $parentFlowHistoryId,
                $triggerData,
                $listenerType
            );

            if (!$flowHistoryId) {
                return false;
            }

            $flowMap = $nextNodId ? $flowExecutorInstance->findNextNode($flow->map, $nextNodId) ?? [] : $flow->map;

            if (empty($flowMap)) {
                return FlowHistoryService::updateFlowHistoryStatus($flowHistoryId);
            }

            $queueData = [
                'tasks'           => $flowMap,
                'flow_id'         => $flow->id,
                'settings'        => $flow->settings,
                'flow_history_id' => $flowHistoryId,
                'listener_type'   => $flow->listener_type
            ];

            $isBackgroundDisabled = (defined('BACKGROUND_PROCESS_DISABLE') && BACKGROUND_PROCESS_DISABLE)
                || (isset($flowSettings['background_process']) && !$flowSettings['background_process']);

            if ($isBackgroundDisabled) {
                $obj = new stdClass();

                $obj->data = $queueData;
                // avoid time limit for long running flows
                if (\function_exists('set_time_limit')) {
                    set_time_limit(0);
                }

                $flowExecutorInstance->executeBatchTasks($obj, true);
            } else {
                $flowExecutorInstance->pushToQueue($queueData)->save()->dispatch();
            }

            return true;
        }

        return false;
    }

    /**
     * Execute the current node and report how the flow should proceed.
     *
     * Condition/router/trigger nodes short-circuit to false (no task work). Action nodes
     * run via the node executor: a PENDING response returns true to pause the batch, an
     * ERROR response notifies the admin and may block (return true) or route to an error
     * branch depending on the flow's onNodeFail setting. Tool nodes run through the tools
     * factory. Any thrown error is logged and swallowed as false.
     *
     * @return bool|mixed true to pause/hold the batch, false to advance, or a tool/iterator response
     */
    protected function task()
    {
        $currentNode = $this->currentNode;

        $flowHistoryId = $this->flowHistoryId;

        $flowId = $this->flowId;

        $onNodeFail = $this->flowSettings['onNodeFail'] ?? 'continue';

        if ($currentNode->type === 'condition' || $currentNode->type === 'router') {
            return false;
        }

        $currentNodeInfo = null;

        try {
            $nodeInstance = GlobalNodes::getInstance($flowId);

            $nodes = $nodeInstance->getAllNodeData();

            $currentNodeInfo = Node::getNodeInfoById($currentNode->id, $nodes);

            switch ($currentNode->type) {
                case 'trigger':
                    GlobalNodeVariables::getInstance($flowHistoryId, $flowId);

                    return false;

                case 'action':
                    $response = $this->nodeExecutor->handleActionNode($currentNodeInfo, $flowHistoryId, $flowId);
                    if ($response === FlowLog::STATUS['PENDING']) {
                        return true;
                    }

                    if ($response === FlowLog::STATUS['ERROR']) {
                        FailedTaskNotifier::send($currentNodeInfo, $flowId);

                        if ($onNodeFail === 'block') {
                            return true;
                        }
                    }

                    return $response === FlowLog::STATUS['ERROR'] && $this->nodeExecutor->hasErrorNodeIdInNextNodes($flowId, $currentNode);

                default:
                    break;
            }

            if (class_exists(FlowToolsFactory::class)) {
                GlobalNodeVariables::getInstance($flowHistoryId, $flowId);

                return FlowToolsFactory::executeToolWithLogging($currentNode, $currentNodeInfo, $flowHistoryId);
            }
        } catch (Throwable $th) {
            $logInstance = LogHandler::getInstance();

            $logInstance->addLog(
                $this->flowHistoryId,
                $currentNode->id,
                FlowLog::STATUS['ERROR'],
                [],
                [
                    'line_number' => $th->getLine(),
                    'file_name'   => $th->getFile(),
                    'message'     => $th->getMessage(),
                ],
                null,
                [
                    'app_slug'     => $currentNodeInfo->app_slug ?? '',
                    'machine_slug' => $currentNodeInfo->machine_slug ?? ''
                ]
            );

            return false;
        }

        return false;
    }

    /**
     * Process one queued batch of flow tasks.
     *
     * Seeds the executor state (flow id, history id, listener type, settings) from the
     * batch, runs the flow map, then either completes the batch (synchronous or no tasks
     * left) or writes the remaining state back so the next dispatch resumes it.
     *
     * @param mixed $batch         queue batch holding the task map and flow metadata
     * @param bool  $isSynchronous run inline without yielding on time/memory limits
     */
    protected function executeBatchTasks($batch, $isSynchronous = false)
    {
        $tasks = $batch->data['tasks'];

        if (is_countable($tasks)) {
            $flowMap = $tasks;
        } else {
            $flowMap[] = $tasks;
        }

        $this->flowId = $batch->data['flow_id'];

        $this->flowHistoryId = $batch->data['flow_history_id'];

        $this->listenerType = $batch->data['listener_type'];

        $this->flowSettings = $batch->data['settings'] ?? [];

        GlobalFlow::setFlowId($this->flowId);

        $batch = $this->processFlowMap($flowMap, $batch, $isSynchronous);

        if (empty($batch->data) || $isSynchronous) {
            $this->batchComplete();
        } else {
            $batch->data['flow_history_id'] = $this->flowHistoryId;
            $batch->data['flow_id'] = $this->flowId;
            $batch->data['listener_type'] = $this->listenerType;
            $batch->data['settings'] = $this->flowSettings;

            $this->update($batch->key, $batch->data);
        }
    }

    protected function handleTaskTimeout()
    {
        $this->flushLogs();
    }

    protected function batchComplete()
    {
        LogService::save(LogHandler::getLogs());

        if ($this->listenerType === FlowModel::LISTENER_TYPE['RUN_ONCE']) {
            $variables = GlobalNodeVariables::getInstance()->getVariables();
            // remove trigger node variables
            if (isset($variables[$this->flowId . '-1'])) {
                unset($variables[$this->flowId . '-1']);
            }
            NodeService::saveNodeVariables($this->flowId, $variables);
        }

        $flow = FlowModel::where('id', $this->flowId)->first();

        $flow->update(['run_count' => ++$flow->run_count])->save();

        FlowHistoryService::updateFlowHistoryStatus($this->flowHistoryId);

        LogHandler::destroy();

        GlobalNodes::destroy();

        GlobalNodeVariables::destroy();

        GlobalFlow::destroy();
    }

    /**
     * Persist the buffered logs and reset the handler.
     */
    private function flushLogs()
    {
        LogService::save(LogHandler::getLogs());

        LogHandler::destroy();
    }

    /**
     * Time or memory limit hit and we're not running synchronously.
     *
     * @param mixed $isSynchronous
     *
     * @return bool
     */
    private function reachedLimit($isSynchronous)
    {
        return ($this->timeExceeded() || $this->memoryExceeded()) && !$isSynchronous;
    }

    /**
     * Write the remaining flow map back onto the batch (or clear it when empty).
     *
     * @param mixed $batch
     */
    private function syncBatchTasks($batch, array $flowMap)
    {
        if ($flowMap !== []) {
            $batch->data['tasks'] = $flowMap;
        } else {
            $batch->data = [];
        }
    }

    private function checkAndCleanupProcessLock()
    {
        $identifier = $this->prefix . $this->action;

        $lock = get_site_transient($identifier . '_process_lock');

        if ($lock && (time() - strtotime($lock) > $this->queueLockTime)) {
            delete_site_transient($identifier . '_process_lock');
        }
    }

    /**
     * Recursively find the node matching $searchId and return its next node(s).
     *
     * @param mixed $node     node tree to search
     * @param mixed $searchId id of the node whose successors are wanted
     *
     * @return mixed the matched node's next node(s), or null if not found
     */
    private function findNextNode($node, $searchId)
    {
        if ($node->id === $searchId) {
            return $node->next ?? null;
        }

        if (isset($node->next)) {
            if (\is_array($node->next)) {
                foreach ($node->next as $childNode) {
                    $result = $this->findNextNode($childNode, $searchId);

                    if ($result !== null) {
                        return $result;
                    }
                }
            }

            return $this->findNextNode($node->next, $searchId);
        }
    }

    /**
     * Walk the flow map, executing each node and queueing its successors.
     *
     * Pops nodes one at a time. A node carrying resume markers replays its saved range
     * instead of re-running task(). A truthy bool response pauses the node (re-syncs the
     * batch and skips successors). Otherwise successors are queued: router/condition nodes
     * fan out, iterator/repeater nodes loop, all others append next. Stops early when a
     * time/memory limit is reached so the batch can resume later.
     *
     * @param array $flowMap       remaining nodes to process
     * @param mixed $batch         queue batch updated with leftover tasks
     * @param bool  $isSynchronous run inline without yielding on time/memory limits
     *
     * @return mixed the batch with its task state synced
     */
    private function processFlowMap($flowMap, $batch, $isSynchronous)
    {
        while (\count($flowMap) > 0) {
            $currentNode = array_shift($flowMap);
            $this->currentNode = $currentNode;

            if (isset($currentNode->_resumeStart)) {
                $response = ['start' => $currentNode->_resumeStart, 'end' => $currentNode->_resumeEnd];
                unset($currentNode->_resumeStart, $currentNode->_resumeEnd);
                GlobalNodeVariables::getInstance($batch->data['flow_history_id'], $batch->data['flow_id']);
            } else {
                $response = $this->task();
            }

            if (\is_bool($response) && $response) {
                $this->syncBatchTasks($batch, $flowMap);

                continue;
            }

            if (isset($currentNode->next)) {
                if (\in_array($currentNode->type, ['router', 'condition'])) {
                    $this->fanOutConditionNodes($currentNode, $flowMap);
                } elseif (\in_array($currentNode->type, ['iterator', 'repeater'])) {
                    $this->runIteratorNode($currentNode, $batch, $response, $isSynchronous, $flowMap);
                } else {
                    $flowMap[] = $currentNode->next;
                }
            }

            $this->syncBatchTasks($batch, $flowMap);

            if ($this->reachedLimit($isSynchronous)) {
                $this->handleTaskTimeout();

                break;
            }
        }

        return $batch;
    }

    /**
     * Queue a router/condition node's children, keeping the default branch last.
     *
     * @param mixed $currentNode
     */
    private function fanOutConditionNodes($currentNode, array &$flowMap)
    {
        $defaultConditionNode = null;

        foreach ($currentNode->next as $childNode) {
            if ($childNode->type === 'default-condition-logic') {
                $defaultConditionNode = $childNode;

                continue;
            }

            $flowMap[] = $childNode;
        }

        if ($defaultConditionNode) {
            $flowMap[] = $defaultConditionNode;
        }
    }

    /**
     * Run an iterator/repeater node's sub-flow once per item.
     *
     * On a time/memory limit it records resume markers on $currentNode and pushes it back
     * onto $flowMap so the next dispatched batch continues where this one stopped.
     *
     * @param mixed $currentNode
     * @param mixed $batch
     * @param mixed $response
     * @param mixed $isSynchronous
     */
    private function runIteratorNode($currentNode, $batch, $response, $isSynchronous, array &$flowMap)
    {
        $iterStart = $response['start'] - 1;
        $iterEnd = $response['end'];
        $timedOut = false;
        $pendingTasks = isset($currentNode->_resumePendingTasks) ? $currentNode->_resumePendingTasks : null;

        unset($currentNode->_resumePendingTasks);

        for ($i = $iterStart; $i < $iterEnd; ++$i) {
            GlobalNodeVariables::getInstance()->setNodeIndexPosition($currentNode->id, $i);

            if ($pendingTasks !== null) {
                $this->processFlowMap($pendingTasks, $batch, $isSynchronous);
                $pendingTasks = null;
            } else {
                $this->processFlowMap([$currentNode->next], $batch, $isSynchronous);
            }

            $this->flushLogs();

            if ($this->reachedLimit($isSynchronous)) {
                $this->handleTaskTimeout();
                $timedOut = true;

                break;
            }
        }

        if (!$timedOut) {
            return;
        }

        $iterPendingTasks = $batch->data['tasks'] ?? [];
        $hasMoreIterations = ($i + 1) < $iterEnd;

        if (!empty($iterPendingTasks) || $hasMoreIterations) {
            // On resume, processFlowMap computes $iterStart = _resumeStart - 1, so
            // _resumeStart = $i + 1 re-enters iteration $i with its pending sub-tasks,
            // and $i + 2 moves on to the next iteration.
            $currentNode->_resumeEnd = $iterEnd;

            if (!empty($iterPendingTasks)) {
                $currentNode->_resumeStart = $i + 1;
                $currentNode->_resumePendingTasks = $iterPendingTasks;
            } else {
                $currentNode->_resumeStart = $i + 2;
            }

            array_unshift($flowMap, $currentNode);
            $batch->data['tasks'] = $flowMap;
        }
    }
}
