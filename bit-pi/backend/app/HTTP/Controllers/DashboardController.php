<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\Model\Connection;
use BitApps\Pi\Model\CustomApp;
use BitApps\Pi\Model\Flow;
use BitApps\Pi\Model\FlowHistory;
use BitApps\Pi\Model\FlowLog;

final class DashboardController
{
    public function index()
    {
        // $validated = $request->validate([
        //     'year' => ['nullable', 'integer'],
        // ]);

        $topExecutedFlows = Flow::select(['id', 'run_count', 'title'])
            ->orderBy('run_count')
            ->desc()
            ->take(3)
            ->get();

        $flowHistoryTable = Config::withDBPrefix('flow_histories');

        $flowTable = Config::withDBPrefix('flows');

        $recentFlowHistories = FlowHistory::leftJoin('flows', "{$flowTable}.id", '=', "{$flowHistoryTable}.flow_id")
            ->orderBy('id')
            ->desc()
            ->take(5)
            ->get(
                [
                    "{$flowHistoryTable}.id",
                    "{$flowHistoryTable}.flow_id",
                    "{$flowTable}.title",
                    "{$flowHistoryTable}.status",
                    "{$flowHistoryTable}.created_at",
                ]
            );

        $totalWorkflows = (int) Flow::count();
        $activeFlows = (int) Flow::where('is_active', Flow::STATUS['ACTIVE'])->count();
        $totalConnections = (int) Connection::where('status', Connection::STATUS['verified'])->count();
        $totalCustomApps = (int) CustomApp::count();
        $failedTasks = $this->getFailedTasks();

        if (\count($recentFlowHistories) > 0) {
            $recentFlowHistories = $recentFlowHistories->map(
                function ($history) {
                    $timestamp = strtotime((string) $history->created_at);

                    if ($timestamp === false) {
                        $history->status = $this->formatHistoryStatus($history->status ?? '');
                        $history->time = '';

                        return $history;
                    }

                    $history->created_at = gmdate('Y/m/d', $timestamp);
                    $history->status = $this->formatHistoryStatus($history->status ?? '');
                    $history->time = gmdate('g:i a', $timestamp);

                    return $history;
                }
            )->toArray();
        }

        return Response::success(
            [
                'activeFlows'           => $activeFlows,
                'failedTasks'           => $failedTasks,
                'topExecutedFlows'      => $topExecutedFlows,
                'recentFlowHistories'   => $recentFlowHistories,
                'monthlyFlowExecutions' => $this->getMonthlyFlowExecutions(),
                'totalConnections'      => $totalConnections,
                'totalCustomApps'       => $totalCustomApps,
                'totalWorkflows'        => $totalWorkflows,
            ]
        );
    }

    private function getMonthlyFlowExecutions()
    {
        $monthLabels = [
            ['month' => 'January'],
            ['month' => 'February'],
            ['month' => 'March'],
            ['month' => 'April'],
            ['month' => 'May'],
            ['month' => 'June'],
            ['month' => 'July'],
            ['month' => 'August'],
            ['month' => 'September'],
            ['month' => 'October'],
            ['month' => 'November'],
            ['month' => 'December'],
        ];

        // GROUP BY / ORDER BY target the `month` select alias, which the query
        // builder would qualify with the table name and MySQL then rejects, so
        // the aggregate runs as a prepared raw statement.
        $monthlyFlowExecutions = FlowHistory::rawPrepared(
            'SELECT COUNT(id) AS run_count, MONTH(created_at) AS month FROM {{identifier:table}} WHERE YEAR(created_at) = %d GROUP BY month ORDER BY month ASC',
            [gmdate('Y')],
            ['table' => Config::withDBPrefix('flow_histories')]
        );

        if (empty($monthlyFlowExecutions) || !is_iterable($monthlyFlowExecutions)) {
            return $monthLabels;
        }

        // $monthLabels is in calendar order, so MySQL's MONTH() (1-12) maps
        // straight onto its index.
        foreach ($monthlyFlowExecutions as $monthlyFlowExecution) {
            $index = (int) $monthlyFlowExecution->month - 1;

            if (isset($monthLabels[$index])) {
                $monthLabels[$index]['run_count'] = (int) $monthlyFlowExecution->run_count;
            }
        }

        return $monthLabels;
    }

    private function getFailedTasks()
    {
        $globalSettings = Config::getOption('global_settings');
        $mailSent = !empty($globalSettings['notify_user']) && !empty($globalSettings['notification_email']);

        $failedTasks = FlowLog::where('status', FlowLog::STATUS['ERROR'])
            ->orderBy('id')
            ->desc()
            ->take(5)
            ->get(['node_id', 'details']);

        if (\count($failedTasks) === 0) {
            return [];
        }

        return $failedTasks
            ->map(
                function ($task) use ($mailSent) {
                    $details = \is_array($task->details) ? $task->details : [];
                    $appSlug = $details['app_slug'] ?? '';
                    $machineSlug = $details['machine_slug'] ?? '';

                    return [
                        'mailSent' => $mailSent,
                        'nodeId'   => $task->node_id,
                        'taskName' => $this->formatTaskName($appSlug, $machineSlug),
                    ];
                }
            )
            ->toArray();
    }

    private function formatTaskName($appSlug, $machineSlug)
    {
        $appLabel = ucwords(str_replace(['-', '_'], ' ', (string) $appSlug));
        $machineLabel = $this->convertMachineSlugToLabel((string) $machineSlug);

        return trim($appLabel . ' - ' . $machineLabel, ' -');
    }

    private function formatHistoryStatus($status)
    {
        return ucwords(str_replace('-', ' ', (string) $status));
    }

    private function convertMachineSlugToLabel($machineSlug)
    {
        $label = preg_replace('/(?<!^)([A-Z])/', ' $1', $machineSlug);

        return ucwords(str_replace(['-', '_'], ' ', (string) $label));
    }
}
