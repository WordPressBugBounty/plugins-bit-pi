<?php

namespace BitApps\Pi\src\Flow;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Helpers\DateTimeHelper;
use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Hooks\Hooks;
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

if (!\defined('ABSPATH')) {
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

            if (\defined('BACKGROUND_PROCESS_DISABLE') && BACKGROUND_PROCESS_DISABLE) {
                $obj = new stdClass();

                $obj->data = $queueData;

                $flowExecutorInstance->executeBatchTasks($obj);
            } else {
                $flowExecutorInstance->pushToQueue($queueData)->save()->dispatch();
            }

            return true;
        }

        return false;
    }

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

                    if ($response === FlowLog::STATUS['ERROR']) {
                        $this->sendTaskFailedNotification($currentNodeInfo, $flowId);

                        if ($onNodeFail === 'block') {
                            return true;
                        }
                    }

                    return $response === FlowLog::STATUS['ERROR'] && $this->nodeExecutor->hasErrorNodeIdInNextNodes($flowId, $currentNode);

                default:
                    break;
            }

            if (class_exists(FlowToolsFactory::class)) {
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

    protected function executeBatchTasks($batch)
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

        global $globalFlowId;

        $globalFlowId = $this->flowId;

        $batch = $this->processFlowMap($flowMap, $batch);

        if (empty($batch->data)) {
            $this->batchComplete();
        } else {
            $this->update($batch->key, $batch->data);
            $this->batchDispatch();
        }
    }

    protected function handleTaskTimeout()
    {
        LogService::save(LogHandler::getLogs());

        LogHandler::destroy();
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

    private function checkAndCleanupProcessLock()
    {
        $identifier = $this->prefix . $this->action;

        $lock = get_site_transient($identifier . '_process_lock');

        if ($lock && (time() - strtotime($lock) > $this->queueLockTime)) {
            delete_site_transient($identifier . '_process_lock');
        }
    }

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

    private function processFlowMap($flowMap, $batch)
    {
        while (\count($flowMap) > 0) {
            $currentNode = array_shift($flowMap);
            $this->currentNode = $currentNode;

            $response = $this->task();

            if (\is_bool($response) && $response) {
                if ($flowMap !== []) {
                    $batch->data['tasks'] = $flowMap;
                } else {
                    $batch->data = [];
                }

                continue;
            }

            if (isset($currentNode->next)) {
                if (\in_array($currentNode->type, ['router', 'condition'])) {
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
                } elseif (\in_array($currentNode->type, ['iterator', 'repeater'])) {
                    global $nodeIndexPosition;

                    for ($i = $response['start'] - 1; $i < $response['end']; ++$i) {
                        $nodeIndexPosition[$currentNode->id] = $i;

                        $this->processFlowMap([$currentNode->next], $batch);

                        LogService::save(LogHandler::getLogs());

                        LogHandler::destroy();
                    }
                } else {
                    $flowMap[] = $currentNode->next;
                }
            }

            if ($flowMap !== []) {
                $batch->data['tasks'] = $flowMap;
            } else {
                $batch->data = [];
            }

            usleep(25000);

            if ($this->timeExceeded() || $this->memoryExceeded()) {
                $this->handleTaskTimeout();

                break;
            }
        }

        return $batch;
    }

    private function sendTaskFailedNotification($nodeInfo, $flowId)
    {
        $globalSettings = Config::getOption('global_settings');

        if (empty($globalSettings['notify_user']) || empty($globalSettings['notification_email'])) {
            return;
        }

        $emailBody = $this->getEmailBody($flowId, $nodeInfo);

        Hooks::addFilter('wp_mail_content_type', (fn () => 'text/html; charset=UTF-8'));

        wp_mail(
            $globalSettings['notification_email'],
            'Failed Task Notification',
            $emailBody
        );

        remove_filter('wp_mail_content_type', (fn () => 'text/html; charset=UTF-8'));
    }

    private function getEmailBody($flowId, $nodeInfo)
    {
        $flowTitle = FlowModel::select('title')->findOne(['id' => $flowId])->title ?? '';

        $emailBody = '<p>Hello, </p>';

        $emailBody .= '<p>We hope you are doing well. We wanted to inform you that a task in your Flow [' . esc_html($flowTitle) . '], has failed to execute as expected.</p>';

        $emailBody .= '<h4>Failed Task Details</h4>';

        $emailBody .= '<ul>';

        $emailBody .= '<li>Task Name: ' . esc_html(ucfirst($nodeInfo['app_slug']) . '-' . $this->convertMachineSlugToLabel($nodeInfo['machine_slug'])) . '</li>';

        $emailBody .= '<li>Node Id: ' . esc_html($nodeInfo['node_id']) . '</li>';

        $emailBody .= '</ul>';

        $emailBody .= '<p>You can review the full workflow details and take further action by visiting the workflow page<p>';

        $emailBody .= '<a href="' . esc_url(admin_url('admin.php?page=' . Config::SLUG . '#/flows/details/' . $flowId)) . '">Click here to view the Flow</a>';

        return $emailBody . '<p> Please check the Flow and take necessary actions to resolve the issue.</p>';
    }

    private function convertMachineSlugToLabel($machineSlug)
    {
        $label = preg_replace('/(?<!^)([A-Z])/', ' $1', $machineSlug);

        return ucwords($label);
    }
}
