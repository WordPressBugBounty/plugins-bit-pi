<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\Model\Flow;

class FlowSettingsController
{
    public function getSettings(Request $request)
    {
        $validated = $request->validate(
            [
                'flow_id' => ['required', 'integer'],
            ]
        );

        $flow = Flow::select('settings')->findOne(['id' => $validated['flow_id']]);

        if (!$flow) {
            return Response::error('Flow not found!');
        }

        if (empty($flow->settings)) {
            return Response::success(Flow::DEFAULT_SETTINGS);
        }

        $mergedSettings = array_merge(Flow::DEFAULT_SETTINGS, $flow->settings);

        return Response::success($mergedSettings);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate(
            [
                'flow_id'             => ['required', 'integer'],
                'settings'            => ['required', 'array'],
                'settings.onNodeFail' => ['nullable', 'string', 'sanitize:text'],
            ]
        );

        $flow = Flow::findOne(['id' => $validated['flow_id']]);

        if (!$flow) {
            return Response::error('Flow not found!');
        }

        if (isset($flow->settings) && \is_array($flow->settings)) {
            $flow->settings = array_merge($flow->settings, $validated['settings']);
        } else {
            $flow->settings = $validated['settings'];
        }

        $flow->save();

        return Response::success('Settings updated successfully!');
    }
}
