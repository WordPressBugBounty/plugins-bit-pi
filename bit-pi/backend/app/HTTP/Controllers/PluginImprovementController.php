<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\Deps\BitApps\WPTelemetry\Telemetry\Telemetry;

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
            Telemetry::report()->trackingOptIn();
        } else {
            Telemetry::report()->trackingOptOut();
        }

        return Response::success(
            [
                'allowTracking' => $validatedData['allowTracking'],
            ]
        );
    }
}
