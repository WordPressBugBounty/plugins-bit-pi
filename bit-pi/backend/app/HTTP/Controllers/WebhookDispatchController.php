<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\Helpers\Hash;
use BitApps\Pi\Helpers\IpAllowlist;
use BitApps\Pi\Model\Flow;
use BitApps\Pi\Model\Webhook;
use BitApps\Pi\src\Flow\FlowExecutor;
use BitApps\Pi\src\Flow\NodeExecutor;

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

        if ($this->isAuthenticationEnabled($webhook) && !$this->hasValidSecret($request, $webhook)) {
            return Response::error('Webhook authentication failed', 401)
                ->header('WWW-Authenticate', 'Bearer realm="Bit Flows Webhook"');
        }

        if (!$this->isRequestIpAllowed($webhook)) {
            return Response::error('Webhook request rejected: your IP address is not allowed', 403);
        }

        $flow = Flow::select(['id', 'title', 'settings', 'is_active', 'map', 'trigger_type', 'listener_type', 'is_hook_capture'])->where('id', $webhook->flow_id)->first();

        if (!$flow) {
            return Response::error('Flow does not exist');
        }

        $triggerClass = (new NodeExecutor())->isExistClass($webhook->app_slug, 'Trigger');

        if ($triggerClass && method_exists($triggerClass, 'handle')) {
            return (new $triggerClass())->handle($request);
        }

        if ($flow->listener_type === Flow::LISTENER_TYPE['NONE'] && $flow->is_active === Flow::STATUS['IN_ACTIVE']) {
            return Response::success('Sorry, the requested action cannot be performed because the flow is currently inactive.');
        }

        FlowExecutor::execute($flow, $this->buildTriggerData($request, $webhook));

        return Response::success('Accepted');
    }

    /**
     * Build the trigger payload handed to the flow, applying the webhook's advanced settings.
     */
    private function buildTriggerData(Request $request, Webhook $webhook): array
    {
        $details = \is_array($webhook->details) ? $webhook->details : [];

        $triggerData = empty($details['json_pass_through'])
            ? $request->all()
            : ['body' => $this->getRawBody($request)];

        if (!empty($details['include_headers'])) {
            $triggerData['headers'] = $this->getRequestHeaders($request);
        }

        if (!empty($details['include_http_method'])) {
            $triggerData['method'] = $request->method();
        }

        return $triggerData;
    }

    /**
     * The unparsed request body, so JSON payloads reach the flow as a string.
     */
    private function getRawBody(Request $request): string
    {
        $rawBody = $request->get_body();

        if (\is_string($rawBody) && $rawBody !== '') {
            return $rawBody;
        }

        // GET requests carry no body at all, so rebuild one from the query params.
        return JSON::encode($request->all());
    }

    /**
     * Incoming request headers, flattened to single values.
     *
     * Credential-bearing headers are dropped so the webhook secret never ends up
     * in flow history or logs.
     */
    private function getRequestHeaders(Request $request): array
    {
        $headers = [];

        foreach ((array) $request->get_headers() as $name => $values) {
            if (\in_array($name, ['authorization', 'cookie'], true)) {
                continue;
            }

            $headers[$name] = \is_array($values) ? implode(', ', $values) : (string) $values;
        }

        return $headers;
    }

    private function isAuthenticationEnabled(Webhook $webhook): bool
    {
        $details = \is_array($webhook->details) ? $webhook->details : [];

        return !empty($details['authentication']);
    }

    /**
     * Whether the incoming request IP passes the webhook's allowlist.
     *
     * An empty allowlist means no restriction. The client IP is taken from
     * REMOTE_ADDR (the real TCP peer) so it cannot be spoofed via headers.
     */
    private function isRequestIpAllowed(Webhook $webhook): bool
    {
        $allowlist = \is_array($webhook->ip_restrictions) ? $webhook->ip_restrictions : [];

        if (empty($allowlist)) {
            return true;
        }

        $clientIp = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';

        return $clientIp !== '' && IpAllowlist::matches($clientIp, $allowlist);
    }

    private function hasValidSecret(Request $request, Webhook $webhook): bool
    {
        $authorization = $request->get_header('authorization');
        if (!\is_string($authorization) || !preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)) {
            return false;
        }

        $details = \is_array($webhook->details) ? $webhook->details : [];
        $encryptedSecret = $details['secret_token'] ?? '';
        $secret = $encryptedSecret ? Hash::decrypt($encryptedSecret) : false;

        return \is_string($secret) && $secret !== '' && hash_equals($secret, trim($matches[1]));
    }
}
