<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Helpers\Arr;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\Model\FlowHistory;

final class HistoryController
{
    /**
     * Get all flow process by flow id.
     *
     * @return array
     */
    public function index(Request $request)
    {
        $validatedData = $request->validate(
            [
                'flow_id'     => ['required', 'integer'],
                'page_limit'  => ['required', 'integer'],
                'page_number' => ['required', 'integer'],
            ]
        );

        $flowHistories = FlowHistory::where('flow_id', $validatedData['flow_id'])
            ->with(
                'logs',
                function ($q) {
                    $q->select('flow_history_id', 'details');
                }
            )
            ->desc()
            ->paginate($validatedData['page_number'], $validatedData['page_limit']);

        array_map(
            function ($history) {
                $totalDuration = 0;

                $totalDataSize = 0;

                foreach ($history['logs'] as $log) {
                    if (isset($log['details']['duration'])) {
                        $totalDuration += $log['details']['duration'];
                    }

                    if (isset($log['details']['data_size'])) {
                        $totalDataSize += $log['details']['data_size'];
                    }
                }

                $history['duration'] = $totalDuration;

                $history['data_size'] = $totalDataSize;

                $history['operations'] = \count($history['logs']);

                unset($history['logs']);

                return $history;
            },
            $flowHistories['data']
        );

        $flowHistories['data'] = $this->groupLogsByParent($flowHistories['data']);

        return Response::success($flowHistories);
    }

    /**
     * Get process logs by process id.
     *
     * @return array
     */
    public function show(Request $request)
    {
        $validated = $request->validate(
            [
                'history_id' => ['required', 'integer'],
            ]
        );

        $flowHistory = FlowHistory::with('logs')->findOne(['id' => $validated['history_id']]);

        $nodeIdsFromLogs = Arr::pluck($flowHistory['logs'], 'node_id');

        if (empty($nodeIdsFromLogs)) {
            return Response::success($flowHistory);
        }

        return Response::success($flowHistory);
    }

    private function groupLogsByParent($logs, $parentLogId = null)
    {
        $nestedLogs = [];

        foreach ($logs as $log) {
            if ($log['parent_history_id'] === $parentLogId) {
                $children = $this->groupLogsByParent($logs, $log['id']);

                if ($children) {
                    $log['children'] = $children;
                }

                $nestedLogs[] = $log;
            }
        }

        return $nestedLogs;
    }
}
