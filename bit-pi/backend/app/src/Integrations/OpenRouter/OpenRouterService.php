<?php

namespace BitApps\Pi\src\Integrations\OpenRouter;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;

final class OpenRouterService
{
    private $http;

    private $baseUrl = 'https://openrouter.ai/api/v1';

    /**
     * OpenRouterService constructor.
     *
     * @param mixed $httpClient
     */
    public function __construct($httpClient)
    {
        $this->http = $httpClient;
    }

    /**
     * Create a chat completion using OpenRouter AI models.
     *
     * @param array       $fieldMapData
     * @param null|bool   $isMemoryEnabled
     * @param null|string $memoryKey
     * @param null|string $contextLength
     *
     * @return array
     */
    public function createChatCompletion($fieldMapData, $isMemoryEnabled = null, $memoryKey = null, $contextLength = null)
    {
        unset($fieldMapData['prompt'], $fieldMapData['content'], $fieldMapData['advance-feature']);
        $endPoint = $this->baseUrl . '/chat/completions';
        $hasMemoryKey = ($isMemoryEnabled === true && $memoryKey !== '');

        if ($hasMemoryKey) {
            $key = 'openRouter-' . $memoryKey;
            $memoryData = Config::getOption($key, []);

            $fieldMapData = array_merge(
                $fieldMapData,
                [
                    'prompt_cache_key' => $key,
                ]
            );

            if (isset($fieldMapData['messages']) && \is_array($fieldMapData['messages'])) {
                $fieldMapData['messages'] = array_map(
                    function ($message) {
                        $message['cache_control'] = ['type' => 'ephemeral'];

                        return $message;
                    },
                    $fieldMapData['messages']
                );
            }

            if (!empty($memoryData)) {
                $fieldMapData['messages'] = array_merge($memoryData, $fieldMapData['messages']);
            }
        }

        $fieldMapData = $this->formatPayloadStructure($fieldMapData);
        $response = $this->http->request($endPoint, 'POST', JSON::encode($fieldMapData));

        if ($hasMemoryKey) {
            OpenRouterService::handleMemoryContext($memoryData, $response, $memoryKey, $contextLength);
        }

        return [
            'response'    => $response,
            'payload'     => $fieldMapData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * List all available models from OpenRouter.
     *
     * @return array
     */
    public function listModels()
    {
        $endPoint = $this->baseUrl . '/models';
        $response = $this->http->request($endPoint, 'GET', []);
        $statusCode = $this->http->getResponseCode();

        if ($response === false || $statusCode !== 200) {
            $response->error = 'Failed to fetch models from OpenRouter.';
        }

        return [
            'response'    => $response,
            'payload'     => [],
            'status_code' => $statusCode
        ];
    }

    /**
     * Fetch credit information for the authenticated account.
     *
     * @return array
     */
    public function fetchCredits()
    {
        $endPoint = $this->baseUrl . '/auth/key';
        $response = $this->http->request($endPoint, 'GET', []);

        $statusCode = $this->http->getResponseCode();

        if ($response === false || $statusCode !== 200) {
            $response->error = 'Failed to fetch credits from OpenRouter.';
        }

        return [
            'response'    => $response,
            'payload'     => [],
            'status_code' => $statusCode
        ];
    }

    public static function handleMemoryContext(array $memoryData, object $response, string $memoryKey, int $contextLength): void
    {
        $key = 'openRouter-' . $memoryKey;
        if (isset($response->choices) && \is_array($response->choices)) {
            foreach ($response->choices as $choice) {
                if (isset($choice->message)) {
                    $memoryData[] = [
                        'role'    => $choice->message->role,
                        'content' => $choice->message->content,
                    ];
                }
            }
        }

        $memoryData = \array_slice($memoryData, -$contextLength);
        Config::updateOption($key, $memoryData);
    }

    private function formatPayloadStructure($data)
    {
        if (isset($data['max_tokens'])) {
            $data['max_tokens'] = (float) $data['max_tokens'];
        }
        if (isset($data['temperature'])) {
            $data['temperature'] = (float) $data['temperature'];
        }
        if (isset($data['top_p'])) {
            $data['top_p'] = (float) $data['top_p'];
        }
        if (isset($data['seed'])) {
            $data['seed'] = (int) $data['seed'];
        }
        if (isset($data['frequency_penalty'])) {
            $data['frequency_penalty'] = (float) $data['frequency_penalty'];
        }
        if (isset($data['presence_penalty'])) {
            $data['presence_penalty'] = (float) $data['presence_penalty'];
        }

        return $data;
    }
}
