<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Config;
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

        // A flow may answer this call itself. Anything able to do so arms here,
        // before the run, so the executor knows to keep it inline.
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Prefixed via Config::VAR_PREFIX (bit_pi_).
        do_action(Config::VAR_PREFIX . 'before_webhook_dispatch', $flow);

        FlowExecutor::execute($flow, $this->buildTriggerData($request, $webhook));

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Prefixed via Config::VAR_PREFIX (bit_pi_).
        $webhookResponse = apply_filters(Config::VAR_PREFIX . 'webhook_response', null, $flow->id);

        if (\is_array($webhookResponse)) {
            $this->sendResponseAndExit($webhookResponse);
        }

        return Response::success('Accepted');
    }

    /**
     * Send the flow's own response and end the request.
     *
     * Nothing after the call site runs: this exits once the body is out.
     *
     * The REST router wraps whatever a controller returns in a JSON envelope, so
     * the body the flow built has to leave before that happens — the caller asked
     * for their own shape, not ours.
     *
     * @param array{statusCode: int, body: string, headers: array} $webhookResponse
     */
    private function sendResponseAndExit(array $webhookResponse)
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            nocache_headers();

            status_header($webhookResponse['statusCode']);

            $headerNames = array_map('strtolower', array_keys($webhookResponse['headers']));

            foreach ($webhookResponse['headers'] as $name => $value) {
                header($name . ': ' . $value, true);
            }

            // The tool names a Content-Type for every shape that carries a body,
            // so this only covers a redirect the flow gave no type of its own.
            if (!\in_array('content-type', $headerNames, true)) {
                header('Content-Type: text/plain; charset=utf-8', true);
            }
        }

        // 204 and 304 are defined as having no body at all.
        if (!\in_array($webhookResponse['statusCode'], [204, 304], true)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,Generic.PHP.ForbiddenFunctions.FoundWithAlternative -- flow-authored body; escaping would corrupt JSON, XML and HTML
            echo $webhookResponse['body'];
        }

        exit;
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
