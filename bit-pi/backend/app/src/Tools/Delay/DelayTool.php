<?php

namespace BitApps\Pi\src\Tools\Delay;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Config;
use BitApps\Pi\Model\Flow;
use BitApps\Pi\Model\FlowLog;
use BitApps\Pi\Services\LogService;
use BitApps\Pi\src\DTO\FlowToolResponseDTO;
use BitApps\Pi\src\Flow\FlowExecutor;
use BitApps\Pi\src\Flow\GlobalNodeVariables;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Log\LogHandler;
use BitApps\Pi\src\Tools\FlowToolsFactory;

class DelayTool
{
    public const DELAY_UNITS = [
        'seconds' => 1,
        'minutes' => 60,
        'hours'   => 3600,
        'days'    => 86400,
        'weeks'   => 604800,
        'months'  => 2592000
    ];

    private const MACHINE_SLUG = 'delay';

    /**
     * Longest delay waited in the running request; longer ones go to WP-Cron.
     */
    private const MAX_IN_REQUEST_DELAY = 59;

    private $nodeInfoProvider;

    private $flowHistoryId;

    private $isTestRun;

    public function __construct(NodeInfoProvider $nodeInfoProvider, $flowHistoryId, $isTestRun)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
        $this->flowHistoryId = $flowHistoryId;
        $this->isTestRun = $isTestRun;
    }

    /**
     * Set delay.
     */
    public function execute()
    {
        $config = $this->nodeInfoProvider->getData()['delay'];

        $nodeId = $this->nodeInfoProvider->getNodeId();

        $eventId = Config::VAR_PREFIX . 'run_scheduled_flow_node';

        $nodeVariableInstance = GlobalNodeVariables::getInstance($this->flowHistoryId, $this->nodeInfoProvider->getFlowId());

        $args = [
            'flowHistoryId' => $this->flowHistoryId,
            'nodeId'        => $nodeId,
        ];

        $details = [
            'unit'         => $config['delayUnit'],
            'value'        => $config['delayValue'],
            'app_slug'     => FlowToolsFactory::APP_SLUG,
            'machine_slug' => self::MACHINE_SLUG,
        ];

        $delayInSeconds = $config['delayValue'] * static::DELAY_UNITS[$config['delayUnit']];

        $isInRequestDelay = $delayInSeconds <= self::MAX_IN_REQUEST_DELAY;

        $data = $isInRequestDelay
            ? $this->waitInRequest($delayInSeconds, $config, $args, $details)
            : $this->scheduleEventWithDelay($eventId, $args, $config);

        if ($this->isTestRun && !$isInRequestDelay) {
            $scheduleStatus = $data['status'] === FlowLog::STATUS['PENDING']
            ? FlowLog::STATUS['SUCCESS']
            : FlowLog::STATUS['ERROR'];

            $message = $scheduleStatus === FlowLog::STATUS['SUCCESS']
                ? 'The execution was delayed for ' . $config['delayValue'] . ' ' . $config['delayUnit']
                : \sprintf('Failed to schedule event %s', $eventId);

            $data['status'] = $scheduleStatus;

            $data['message'] = $message;

            wp_clear_scheduled_hook($eventId, $args);
        }

        $nodeVariableInstance->setVariables($nodeId, $data);

        $nodeVariableInstance->setNodeResponse($nodeId, $data);

        return FlowToolResponseDTO::create(
            $data['status'],
            $args,
            $data,
            $data['message'],
            $details,
            !$isInRequestDelay,
            null,
            !$isInRequestDelay
        );
    }

    /**
     * Execute Delay flow.
     *
     * @param int   $flowHistoryId
     * @param mixed $nodeId
     */
    public static function runScheduledFlowNode($flowHistoryId, $nodeId)
    {
        $flowId = explode('-', $nodeId)[0];

        $flowLogs = FlowLog::select(['node_id', 'status'])->where('flow_history_id', $flowHistoryId)->get();

        if ($flowLogs) {
            $flowNodes = Flow::select(['map', 'listener_type', 'is_hook_capture', 'id', 'is_active'])->with(
                'nodes',
                function ($query) {
                    $query->select(['*']);
                }
            )->where('id', $flowId)->first();

            if ($flowNodes && !empty($flowNodes->nodes) && !empty($flowNodes->map)) {
                $flowLog = FlowLog::where('flow_history_id', $flowHistoryId)->where('node_id', $nodeId)->first();

                $flowLog->update(['status' => FlowLog::STATUS['COMPLETED']]);

                wp_clear_scheduled_hook(Config::VAR_PREFIX . 'execute_delayed_flow', ['flowHistoryId' => $flowHistoryId, 'nodeId' => $nodeId]);

                FlowExecutor::execute($flowNodes, [], $flowHistoryId, null, $nodeId);
            }
        }
    }

    /**
     * Waits in the running request: WP-Cron spawns at most once a minute and only on site traffic,
     * so it cannot time a shorter delay. A node test run skips the wait.
     *
     * The node's log is saved as pending before the wait and completed after it, so the log shows
     * the delay while it runs; the buffered logs of earlier nodes are saved first to keep their order.
     *
     * @param float|int $delayInSeconds
     * @param array     $config
     * @param array     $args
     * @param array     $details
     */
    private function waitInRequest($delayInSeconds, $config, $args, $details)
    {
        $duration = $config['delayValue'] . ' ' . $config['delayUnit'];

        $data = [
            'status'  => FlowLog::STATUS['SUCCESS'],
            'message' => 'The execution was delayed for ' . $duration,
        ];

        if ($this->isTestRun) {
            return $data;
        }

        LogService::save(LogHandler::getLogs());
        LogHandler::destroy();

        $log = FlowLog::insert(
            [
                'flow_history_id' => $this->flowHistoryId,
                'node_id'         => $this->nodeInfoProvider->getNodeId(),
                'status'          => FlowLog::STATUS['PENDING'],
                'input'           => $args,
                'output'          => [],
                'messages'        => 'Waiting for ' . $duration,
                'details'         => $details,
            ]
        );

        sleep((int) $delayInSeconds);

        if ($log) {
            // The model returned by insert() does not track later changes, so update by id.
            FlowLog::where('id', $log->id)->update(
                [
                    'status'   => $data['status'],
                    'output'   => $data,
                    'messages' => $data['message'],
                ]
            );
        }

        return $data;
    }

    private function scheduleEventWithDelay($eventId, $args, $config)
    {
        if (wp_next_scheduled($eventId)) {
            return [
                'status'  => FlowLog::STATUS['PENDING'],
                'message' => 'The event ' . $eventId . ' is already scheduled',
            ];
        }

        $delayInSeconds = $config['delayValue'] * static::DELAY_UNITS[$config['delayUnit']];

        $isScheduled = wp_schedule_single_event(time() + $delayInSeconds, $eventId, $args);

        if ($isScheduled) {
            return [
                'status'  => FlowLog::STATUS['PENDING'],
                'message' => 'The execution has been postponed for a duration of ' . $config['delayValue'] . ' ' . $config['delayUnit'],
            ];
        }

        return [
            'status'  => FlowLog::STATUS['ERROR'],
            'message' => 'Failed to schedule event ' . $eventId,
        ];
    }
}
