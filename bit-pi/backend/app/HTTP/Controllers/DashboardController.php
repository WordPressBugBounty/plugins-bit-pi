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

        $recentFlowHistories = array_map(
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
            },
            \is_array($recentFlowHistories) ? $recentFlowHistories : []
        );

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

        $monthlyFlowExecutions = FlowHistory::select('COUNT(id) AS run_count', 'MONTH(created_at) AS month')
            ->groupBy('month')
            ->orderBy('month')
            ->where('YEAR(created_at)', gmdate('Y'))
            ->get();

        foreach ($monthLabels as $key => $label) {
            foreach ($monthlyFlowExecutions as $monthlyFlowExecution) {
                if (gmdate('F', gmmktime(0, 0, 0, $monthlyFlowExecution['month'], 1)) === $label['month']) {
                    $monthLabels[$key]['run_count'] = $monthlyFlowExecution['run_count'];
                }
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

        return array_map(
            function ($task) use ($mailSent) {
                $details = \is_array($task->details) ? $task->details : [];
                $appSlug = $details['app_slug'] ?? '';
                $machineSlug = $details['machine_slug'] ?? '';

                return [
                    'mailSent' => $mailSent,
                    'nodeId'   => $task->node_id,
                    'taskName' => $this->formatTaskName($appSlug, $machineSlug),
                ];
            },
            \is_array($failedTasks) ? $failedTasks : []
        );
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
