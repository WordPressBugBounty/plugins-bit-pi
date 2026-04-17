<?php

namespace BitApps\Pi\src\Integrations\Claude;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;

class ClaudeHelper
{
    private const BASE_URL = 'https://api.anthropic.com/v1';

    private $http;

    private $headers;

    public function __construct($headers)
    {
        $this->http = new HttpClient();
        $this->headers = $headers;
    }

    public function askClaude($data)
    {
        $payload = [
            'model'      => $data['model'] ?? 'claude-3-haiku-20240307',
            'max_tokens' => isset($data['max_tokens']) ? (int) $data['max_tokens'] : 1024,
        ];
        $payload['messages'] = [
            ['role' => 'user', 'content' => $data['message']],
        ];
        $payload = $this->setFeaturePayload($payload, $data);

        $url = self::BASE_URL . '/messages';
        $response = $this->http->request($url, 'POST', JSON::encode($payload), $this->headers);

        return [
            'response'    => $response,
            'payload'     => $payload,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    public function createCompletion($messages, $data)
    {
        $payload = [
            'model'      => $data['model'] ?? 'claude-3-haiku-20240307',
            'max_tokens' => isset($data['max_tokens']) ? (int) $data['max_tokens'] : 1024,
        ];
        $payload['messages'] = array_map(
            function ($msg) {
                return [
                    'role'    => $msg['role'],
                    'content' => $msg['value']
                ];
            },
            $messages
        );

        $payload = $this->setFeaturePayload($payload, $data);

        $url = self::BASE_URL . '/messages';
        $response = $this->http->request($url, 'POST', JSON::encode($payload), $this->headers);

        return [
            'response'    => $response,
            'payload'     => $payload,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    private function setFeaturePayload($payload, $data)
    {
        if (isset($data['temperature'])) {
            $payload['temperature'] = (float) $data['temperature'];
        }
        if (isset($data['top_p'])) {
            $payload['top_p'] = (float) $data['top_p'];
        }
        if (isset($data['top_k'])) {
            $payload['top_k'] = (int) $data['top_k'];
        }
        if (isset($data['stop_sequences'])) {
            $payload['stop_sequences'] = \is_array($data['stop_sequences']) ? $data['stop_sequences'] : [$data['stop_sequences']];
        }

        return $payload;
    }
}
