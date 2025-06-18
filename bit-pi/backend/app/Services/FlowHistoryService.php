<?php

namespace BitApps\Pi\Services;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\HTTP\Controllers\GlobalSettingsController;
use BitApps\Pi\Model\FlowHistory;
use BitApps\Pi\Model\FlowLog;

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

    public static function createFlowHistory($flowId, $flowHistoryId, $parentFlowHistoryId)
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
