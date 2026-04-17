<?php

namespace BitApps\Pi\Services;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\HTTP\Controllers\GlobalSettingsController;
use BitApps\Pi\Model\Flow;
use BitApps\Pi\Model\FlowHistory;
use BitApps\Pi\Model\FlowLog;
use BitApps\Pi\Model\FlowNode;

class FlowHistoryService
{
    public static function updateFlowHistoryStatus($flowHistoryId)
    {
        $nodeProcessPendingCheck = FlowLog::where('flow_history_id', $flowHistoryId)->where('status', FlowLog::STATUS['PENDING'])->first();

        if ($nodeProcessPendingCheck) {
            return;
        }

        $table = Config::get('WP_DB_PREFIX') . Config::VAR_PREFIX . 'flow_logs';

        $query = "SELECT
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) AS success,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) AS error,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending FROM {$table}
                WHERE flow_history_id=%d";

        $results = FlowLog::raw($query, [$flowHistoryId]);

        if (!$results) {
            return;
        }

        $flowHistoryStatus = (new self())->getFlowHistoryStatus($results[0]);

        return FlowHistory::findOne(['id' => $flowHistoryId])
            ->update(
                ['status' => $flowHistoryStatus]
            )->save();
    }

    public static function createHistoryWithTriggerNode($flowId, $flowHistoryId, $parentFlowHistoryId, $triggerData, $listenerType = null)
    {
        if (!$flowHistoryId) {
            $flowHistory = FlowHistory::insert(
                [
                    'flow_id'           => $flowId,
                    'parent_history_id' => $parentFlowHistoryId,
                    'status'            => 'processing'
                ]
            );

            if (!$flowHistory) {
                return false;
            }

            $flowHistoryId = $flowHistory->id;

            $triggerNode = FlowNode::where('node_id', $flowId . '-1')->first();

            if ($triggerNode && isset($triggerNode->app_slug, $triggerNode->machine_slug)) {
                if ($listenerType === Flow::LISTENER_TYPE['RUN_ONCE']) {
                    NodeService::saveNodeVariables($flowId, $triggerData, $flowId . '-1');
                }

                $createdLog = LogService::save(
                    [
                        'flow_history_id' => $flowHistoryId,
                        'node_id'         => $flowId . '-1',
                        'status'          => FlowLog::STATUS['SUCCESS'],
                        'input'           => [],
                        'output'          => JSON::maybeEncode($triggerData),
                        'messages'        => 'trigger node executed successfully.',
                        'details'         => [
                            'app_slug'     => $triggerNode->app_slug,
                            'machine_slug' => $triggerNode->machine_slug
                        ]
                    ]
                );

                if (!$createdLog) {
                    return false;
                }
            }
        }

        return $flowHistoryId;
    }

    public static function flowHistoryCleanup()
    {
        $globalSettings = Config::getOption('global_settings');

        $globalDefaultSettings = (new GlobalSettingsController())->getGlobalDefaultSettings();

        $interval = $globalSettings['preserve_logs'] ?? $globalDefaultSettings['preserve_logs'];

        return FlowHistory::whereRaw('DATE_ADD(created_at, INTERVAL %d DAY) < CURRENT_DATE', [$interval])->delete();
    }

    private function getFlowHistoryStatus($status)
    {
        $totalError = (int) $status->error;

        $totalSuccess = (int) $status->success;

        $totalPending = (int) $status->pending;

        if ($totalError === null || $totalSuccess === null) {
            return false;
        }

        $flowHistoryStatus = FlowHistory::STATUS['PROCESSING'];

        if ($totalPending > 0) {
            return $flowHistoryStatus;
        }

        if ($totalError === 0) {
            $flowHistoryStatus = FlowHistory::STATUS['SUCCESS'];
        } elseif ($totalSuccess === 0 || $totalSuccess === 1) {
            $flowHistoryStatus = FlowHistory::STATUS['FAILED'];
        } elseif ($totalSuccess >= 2) {
            $flowHistoryStatus = FlowHistory::STATUS['PARTIAL_SUCCESS'];
        }

        return $flowHistoryStatus;
    }
}
