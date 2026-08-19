<?php

namespace BitApps\Pi\Services;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Config;
use BitApps\Pi\Model\Webhook;

/**
 * Webhook logic shared by the admin controller and the MCP tools.
 *
 * Both surfaces create webhooks and build callback URLs; keeping the rules
 * here means they cannot drift apart (the MCP copy once missed the
 * authentication default and a query-binding fix).
 */
class WebhookService
{
    /**
     * The public callback URL an external service posts to.
     */
    public static function callbackUrl(string $webhookSlug): string
    {
        return Config::get('API_URL')['base'] . '/webhook/callback/' . $webhookSlug;
    }

    /**
     * Detach whatever webhook is currently connected to the flow.
     *
     * A flow holds at most one webhook, so this runs before connecting
     * another one.
     */
    public static function detachFromFlow(int $flowId): void
    {
        // TODO: replace raw query if possible

        Webhook::raw('UPDATE ' . Config::withDBPrefix('webhooks') . ' SET flow_id = null WHERE flow_id = %d', [$flowId]);
    }

    /**
     * Create a webhook, detaching the flow's previous webhook when a flow id
     * is given.
     *
     * @return false|Webhook
     */
    public static function create(string $title, string $appSlug, ?int $flowId = null)
    {
        $webhookData = [
            'title'        => $title,
            'app_slug'     => $appSlug,
            'webhook_slug' => wp_generate_uuid4(),
            'details'      => ['authentication' => false],
        ];

        if ($flowId) {
            self::detachFromFlow($flowId);

            $webhookData['flow_id'] = $flowId;
        }

        return Webhook::insert($webhookData);
    }
}
