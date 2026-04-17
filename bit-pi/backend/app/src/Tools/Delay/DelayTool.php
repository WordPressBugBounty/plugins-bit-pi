<?php

namespace BitApps\Pi\src\Tools\Delay;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Config;
use BitApps\Pi\Model\Flow;
use BitApps\Pi\Model\FlowLog;
use BitApps\Pi\src\DTO\FlowToolResponseDTO;
use BitApps\Pi\src\Flow\FlowExecutor;
use BitApps\Pi\src\Flow\GlobalNodeVariables;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Tools\FlowToolsFactory;

class DelayTool
{
    public const DELAY_UNITS = [
        'minutes' => 60,
        'hours'   => 3600,
        'days'    => 86400,
        'weeks'   => 604800,
        'months'  => 2592000
    ];

    private const MACHINE_SLUG = 'delay';

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

        $data = $this->scheduleEventWithDelay($eventId, $args, $config);

        if ($this->isTestRun) {
            $scheduleStatus = $data['status'] === FlowLog::STATUS['PENDING']
            ? FlowLog::STATUS['SUCCESS']
            : FlowLog::STATUS['ERROR'];

            $message = $scheduleStatus === FlowLog::STATUS['SUCCESS']
                ? \sprintf('Successfully scheduled event %s', $eventId)
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
            true
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

                $flowLog->update(['status' => FlowLog::STATUS['COMPLETED']])->save();

                wp_clear_scheduled_hook(Config::VAR_PREFIX . 'execute_delayed_flow', ['flowHistoryId' => $flowHistoryId, 'nodeId' => $nodeId]);

                FlowExecutor::execute($flowNodes, [], $flowHistoryId, null, $nodeId);
            }
        }
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
