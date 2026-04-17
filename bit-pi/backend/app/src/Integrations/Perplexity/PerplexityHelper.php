<?php

namespace BitApps\Pi\src\Integrations\Perplexity;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;

class PerplexityHelper
{
    private const BASE_URL = 'https://api.perplexity.ai/chat';

    private $http;

    private $headers;

    public function __construct($headers)
    {
        $this->http = new HttpClient();
        $this->headers = $headers;
    }

    public function askPerplexity($data)
    {
        $payload = [
            'model' => $data['model']
        ];
        $payload['messages'] = [
            ['role' => 'user', 'content' => $data['message']],
        ];
        $payload = $this->setFeaturePayload($payload, $data);
        $url = self::BASE_URL . '/completions';
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
            'model' => $data['model']
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
        $url = self::BASE_URL . '/completions';
        $response = $this->http->request($url, 'POST', JSON::encode($payload), $this->headers);

        return [
            'response'    => $response,
            'payload'     => $payload,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    private function setFeaturePayload($payload, $data)
    {
        if (isset($data['max_tokens'])) {
            $payload['max_tokens'] = (float) $data['max_tokens'];
        }
        if (isset($data['temperature'])) {
            $payload['temperature'] = (float) $data['temperature'];
        }
        if (isset($data['top_p'])) {
            $payload['top_p'] = (float) $data['top_p'];
        }
        if (isset($data['top_k'])) {
            $payload['top_k'] = (int) $data['top_k'];
        }
        if (isset($data['frequency_penalty'])) {
            $payload['frequency_penalty'] = (float) $data['frequency_penalty'];
        }
        if (isset($data['presence_penalty'])) {
            $payload['presence_penalty'] = (float) $data['presence_penalty'];
        }

        return $payload;
    }
}
