<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\Model\Flow;
use BitApps\Pi\Model\Webhook;
use BitApps\Pi\src\Flow\FlowExecutor;

final class WebhookDispatchController
{
    /**
     * Capture webhook response.
     *
     * @return Webhook response
     */
    public function handleWebhook(Request $request)
    {
        $validateData = $request->validate(
            [
                'trigger_id' => ['required', 'string', 'sanitize:text']
            ]
        );

        $triggerId = $validateData['trigger_id'];

        if (!wp_is_uuid($triggerId)) {
            return Response::error('Invalid trigger id');
        }

        $request->__unset('trigger_id');

        $webhook = Webhook::where('webhook_slug', $triggerId)->whereNotNull('flow_id')->first();

        if (!$webhook) {
            return Response::error('Webhook not found');
        }

        $flow = Flow::select(['id', 'title', 'settings', 'is_active', 'map', 'trigger_type', 'listener_type', 'is_hook_capture'])->where('id', $webhook->flow_id)->first();

        if (!$flow) {
            return Response::error('Flow does not exist');
        }

        if ($flow->listener_type === Flow::LISTENER_TYPE['NONE'] && $flow->is_active === Flow::STATUS['IN_ACTIVE']) {
            return Response::success('Sorry, the requested action cannot be performed because the flow is currently inactive.');
        }

        FlowExecutor::execute($flow, $request->all());

        return Response::success('Accepted');
    }
}
