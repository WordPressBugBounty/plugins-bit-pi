<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\Helpers\Hash;
use BitApps\Pi\Helpers\IpAllowlist;
use BitApps\Pi\HTTP\Requests\WebhookAdvancedSettingsRequest;
use BitApps\Pi\HTTP\Requests\WebhookAuthenticationRequest;
use BitApps\Pi\HTTP\Requests\WebhookIndexRequest;
use BitApps\Pi\HTTP\Requests\WebhookRequest;
use BitApps\Pi\HTTP\Requests\WebhookUpdateRequest;
use BitApps\Pi\HTTP\Requests\WebhookUpdateTitleRequest;
use BitApps\Pi\Model\Webhook;
use BitApps\Pi\Services\WebhookService;

final class WebhookController
{
    /**
     * Advanced settings stored as plain booleans in the webhook details.
     */
    private const BOOLEAN_SETTINGS = ['include_headers', 'include_http_method', 'json_pass_through'];

    /**
     * Get all webhooks.
     *
     * @return array webhooks
     */
    public function index(WebhookIndexRequest $request)
    {
        $validated = $request->validated();

        $query = Webhook::select(['id', 'flow_id', 'title', 'app_slug', 'webhook_slug', 'ip_restrictions', 'details', 'created_at']);

        if (isset($validated['flowId'])) {
            $query->where(fn ($q) => $q->where('flow_id', $validated['flowId'])->orWhere('flow_id', null));
        }

        if (isset($validated['appSlug'])) {
            $query->where('app_slug', $validated['appSlug']);
        }

        $webhooks = $query->desc()->get();

        // No rows comes back as a plain array rather than a collection.
        if (empty($webhooks)) {
            return Response::success([]);
        }

        $webhooks = $webhooks->map(
            function ($webhook) {
                $webhook->url = WebhookService::callbackUrl($webhook->webhook_slug);
                $webhook->authentication = $this->isAuthenticationEnabled($webhook);
                $webhook->secret_token = $this->getAuthenticationSecret($webhook);

                $advancedSettings = $this->getAdvancedSettings(
                    \is_array($webhook->details) ? $webhook->details : [],
                    $webhook->ip_restrictions
                );

                foreach ($advancedSettings as $key => $value) {
                    $webhook->{$key} = $value;
                }

                // details holds the encrypted secret; it must not reach the client.
                unset($webhook->details);

                return $webhook;
            }
        );

        return Response::success($webhooks);
    }

    /**
     * Store webhook.
     *
     * @return collection webhook
     */
    public function store(WebhookRequest $request)
    {
        $validated = $request->validated();

        $insert = WebhookService::create(
            $validated['title'],
            $validated['app_slug'],
            isset($validated['flow_id']) ? (int) $validated['flow_id'] : null
        );

        if (!$insert) {
            return Response::error('Error creating webhook');
        }

        return Response::success(
            [
                'id'             => $insert->id,
                'title'          => $insert->title,
                'app_slug'       => $insert->app_slug,
                'webhook_slug'   => $insert->webhook_slug,
                'url'            => WebhookService::callbackUrl($insert->webhook_slug),
                'authentication' => false,
                'secret_token'   => '',
            ] + $this->getAdvancedSettings([])
        );
    }

    /**
     * Enable, regenerate, or disable secret-token authentication for a webhook.
     *
     * @return Webhook webhook
     */
    public function updateAuthentication(WebhookAuthenticationRequest $request)
    {
        $validated = $request->validated();

        $webhook = Webhook::findOne(['id' => $validated['webhook']]);
        if (!$webhook || !$webhook->id) {
            return Response::error('Webhook not found');
        }

        $details = \is_array($webhook->details) ? $webhook->details : [];
        $authenticationEnabled = filter_var(
            $validated['authentication'],
            FILTER_VALIDATE_BOOLEAN
        );
        $regenerateSecret = !empty($validated['regenerate_secret']);

        $details['authentication'] = $authenticationEnabled;

        if (!$authenticationEnabled) {
            unset($details['secret_token']);
        } elseif ($regenerateSecret || empty($details['secret_token'])) {
            $details['secret_token'] = Hash::encrypt($this->generateAuthenticationSecret());
        }

        // update() persists on its own and returns the model, or false when the
        // query fails. A no-op update still counts as success.
        if (!$webhook->update(['details' => $details])) {
            return Response::error('Failed to update webhook authentication');
        }

        return Response::success(
            [
                'id'             => $webhook->id,
                'authentication' => $authenticationEnabled,
                'secret_token'   => $authenticationEnabled
                    ? Hash::decrypt($details['secret_token'])
                    : '',
            ]
        );
    }

    /**
     * Update the advanced settings of a webhook.
     *
     * Only the keys present in the request are changed, so the UI can save a
     * single toggle without sending the whole settings object.
     *
     * @return Webhook webhook
     */
    public function updateAdvancedSettings(WebhookAdvancedSettingsRequest $request)
    {
        $validated = $request->validated();

        $webhook = Webhook::findOne(['id' => $validated['webhook']]);
        if (!$webhook || !$webhook->id) {
            return Response::error('Webhook not found');
        }

        $details = \is_array($webhook->details) ? $webhook->details : [];
        $ipRestrictions = \is_array($webhook->ip_restrictions) ? $webhook->ip_restrictions : [];
        $changes = [];

        if (\array_key_exists('ip_restrictions', $validated)) {
            $ipRestrictions = $this->sanitizeIpRestrictions($validated['ip_restrictions']);

            if (\is_string($ipRestrictions)) {
                // translators: %s: the invalid IP address or CIDR range
                return Response::error(\sprintf(__('Invalid IP address or CIDR range: %s', 'bit-pi'), $ipRestrictions));
            }

            $changes['ip_restrictions'] = $ipRestrictions;
        }

        $booleansChanged = false;

        foreach (self::BOOLEAN_SETTINGS as $setting) {
            if (\array_key_exists($setting, $validated)) {
                $details[$setting] = filter_var($validated[$setting], FILTER_VALIDATE_BOOLEAN);
                $booleansChanged = true;
            }
        }

        if ($booleansChanged) {
            $changes['details'] = $details;
        }

        if ($changes && !$webhook->update($changes)) {
            return Response::error('Failed to update webhook advanced settings');
        }

        return Response::success(
            ['id' => $webhook->id] + $this->getAdvancedSettings($details, $ipRestrictions)
        );
    }

    /**
     * Update webhook.
     *
     * @param Webhook $request
     *
     * @return int webhook id
     */
    public function update(WebhookUpdateRequest $request, Webhook $webhook)
    {
        $validated = $request->validated();

        $this->removeWebhookByFlowId($validated['flow_id']);

        $webhook->update(['flow_id' => $validated['flow_id']]);

        return Response::success(['id' => $webhook->id]);
    }

    /**
     * Update webhook title.
     *
     * @return Webhook webhook
     */
    public function updateTitle(WebhookUpdateTitleRequest $request)
    {
        $validated = $request->validated();

        $webhook = Webhook::findOne(['id' => $validated['webhook']]);
        if (!$webhook->id) {
            return Response::error('Webhook not found');
        }

        if (!$webhook->update(['title' => $validated['title']])) {
            return Response::error('Failed to update webhook title');
        }

        // Only the changed field goes back; the full model would ship the
        // encrypted secret in details to the browser.
        return Response::success(['id' => $webhook->id, 'title' => $webhook->title]);
    }

    public function removeWebhookByFlowId($flowId)
    {
        WebhookService::detachFromFlow((int) $flowId);
    }

    /**
     * Destroy webhook.
     *
     * @return int webhook id
     */
    public function destroy(Webhook $webhook)
    {
        $flowId = $webhook->flow_id;

        if ($flowId) {
            return Response::error('The Webhook is already connected to ' . $webhook->flow->title);
        }

        $webhook->delete();

        return Response::success($webhook->id);
    }

    private function isAuthenticationEnabled(Webhook $webhook): bool
    {
        $details = \is_array($webhook->details) ? $webhook->details : [];

        return !empty($details['authentication']);
    }

    private function getAuthenticationSecret(Webhook $webhook): string
    {
        $details = \is_array($webhook->details) ? $webhook->details : [];

        if (empty($details['secret_token'])) {
            return '';
        }

        $secret = Hash::decrypt($details['secret_token']);

        return \is_string($secret) ? $secret : '';
    }

    private function generateAuthenticationSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * The advanced settings of a webhook, with defaults filled in.
     *
     * The IP allowlist lives in its own column; the booleans have no columns of
     * their own and stay in the details blob.
     *
     * @param array $details        the webhook details column
     * @param mixed $ipRestrictions the webhook's ip_restrictions column
     */
    private function getAdvancedSettings(array $details, $ipRestrictions = null): array
    {
        $settings = [
            'ip_restrictions' => \is_array($ipRestrictions) ? array_values($ipRestrictions) : [],
        ];

        foreach (self::BOOLEAN_SETTINGS as $setting) {
            $settings[$setting] = !empty($details[$setting]);
        }

        return $settings;
    }

    /**
     * Validate and normalize an IP allowlist.
     *
     * @param mixed $entries raw list from the request
     *
     * @return array|string the cleaned allowlist, or the first invalid entry
     */
    private function sanitizeIpRestrictions($entries)
    {
        $ipRestrictions = [];

        foreach (\is_array($entries) ? $entries : [] as $entry) {
            $entry = trim(sanitize_text_field((string) $entry));

            if ($entry === '') {
                continue;
            }

            if (!IpAllowlist::isValidEntry($entry)) {
                return $entry;
            }

            $ipRestrictions[] = $entry;
        }

        return array_values(array_unique($ipRestrictions));
    }
}
