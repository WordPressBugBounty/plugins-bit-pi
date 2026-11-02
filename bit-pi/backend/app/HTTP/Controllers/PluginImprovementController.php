<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\Deps\BitApps\WPTelemetry\Telemetry\Telemetry;
use BitApps\Pi\Model\CustomApp;
use BitApps\Pi\Model\Flow;

final class PluginImprovementController
{
    public function getOpt()
    {
        return Response::success(
            [
                'allowTracking' => Config::getOption('allow_tracking', false),
            ]
        );
    }

    public function updateOpt(Request $request)
    {
        $validatedData = $request->validate(
            [
                'allowTracking' => ['required', 'boolean'],
            ]
        );

        if ($validatedData['allowTracking']) {
            Telemetry::report()->trackingOptIn('settings');
        } else {
            Telemetry::report()->trackingOptOut();
        }

        return Response::success(
            [
                'allowTracking' => $validatedData['allowTracking'],
            ]
        );
    }

    public function filterTrackingData($data)
    {
        $flowsWithNodes = Flow::select(['id', 'run_count', 'is_active as status'])
            ->with(
                'nodes',
                function ($query) {
                    $query->select(['flow_id', 'app_slug as app', 'machine_slug as machine']);
                }
            )->all();


        $customAppsWithMachines = CustomApp::select(['id', 'name', 'status'])->with(
            'customMachines',
            function ($query) {
                $query->select(['custom_app_id', 'name', 'status', 'app_type', 'trigger_type']);
            }
        )->all();


        $data['flows'] = array_map(
            function ($flow) {
                unset($flow['id']);

                if (isset($flow['nodes']) && \is_array($flow['nodes'])) {
                    foreach ($flow['nodes'] as $key => $node) {
                        unset($flow['nodes'][$key]['flow_id']);
                    }
                }

                return $flow;
            },
            \is_array($flowsWithNodes) ? $flowsWithNodes : $flowsWithNodes->toArray()
        );

        $data['customApps'] = array_map(
            function ($customApp) {
                unset($customApp['id']);

                if (isset($customApp['customMachines']) && \is_array($customApp['customMachines'])) {
                    foreach ($customApp['customMachines'] as $key => $machine) {
                        unset($customApp['customMachines'][$key]['custom_app_id']);
                    }
                }

                return $customApp;
            },
            \is_array($customAppsWithMachines) ? $customAppsWithMachines : $customAppsWithMachines->toArray()
        );

        $globalSettings = Config::getOption('global_settings', []);

        $data['usingCloudCron'] = $globalSettings['use_cloud_cron'] ?? false;

        return $data;
    }
}
