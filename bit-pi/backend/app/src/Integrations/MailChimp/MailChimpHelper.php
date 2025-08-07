<?php

namespace BitApps\Pi\src\Integrations\MailChimp;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use BitApps\Pi\Helpers\Hash;

final class MailChimpHelper
{
    public function setMailchimpWebhook(Request $request)
    {
        $validated = $request->validate(
            [
                'token'         => ['required', 'string'],
                'dataCenter'    => ['required', 'string'],
                'audienceList'  => ['required', 'string'],
                'webhookEvent'  => ['required', 'string'],
                'webhookSource' => ['required', 'string'],
                'webhookUrl'    => ['required', 'url'],
            ]
        );

        $token = Hash::decrypt($validated['token']);
        $dataCenter = $validated['dataCenter'];
        $audienceList = $validated['audienceList'];
        $webhookEvent = $validated['webhookEvent'];
        $webhookSource = $validated['webhookSource'];
        $webhookUrl = filter_var($validated['webhookUrl'], FILTER_SANITIZE_URL);

        $httpClient = new HttpClient(
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
            ]
        );

        $body = [
            'events'  => [$webhookEvent => true],
            'sources' => [$webhookSource => true],
            'url'     => $webhookUrl
        ];

        $endpoint = "https://{$dataCenter}.api.mailchimp.com/3.0/lists/{$audienceList}/webhooks";

        return $httpClient->request(
            $endpoint,
            'POST',
            wp_json_encode($body)
        );
    }
}
