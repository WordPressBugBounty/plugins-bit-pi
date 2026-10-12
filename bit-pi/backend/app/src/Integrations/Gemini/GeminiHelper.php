<?php

namespace BitApps\Pi\src\Integrations\Gemini;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use finfo;

class GeminiHelper
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/';

    /**
     * Preview ids Google retired when these models went GA. Flows saved against
     * the old dropdown still hold them, and the node keeps its saved value until
     * someone opens and re-saves it.
     */
    private const RETIRED_MODEL_IDS = [
        'gemini-2.5-flash-image-preview' => 'gemini-2.5-flash-image',
        'gemini-3-pro-image-preview'     => 'gemini-3-pro-image',
    ];

    private $http;

    private $headers;

    public function __construct($headers)
    {
        $this->http = new HttpClient();
        $this->headers = $headers;
    }

    public function askGemini($data, $apiKey)
    {
        $contents = [
            [
                'parts' => [
                    ['text' => $data['message']],
                ]
            ],
        ];

        $payload = ['contents' => $contents];
        $payload = $this->setFeaturePayload($payload, $data);
        $url = self::BASE_URL . "{$data['model']}:generateContent?key={$apiKey}";

        $response = $this->http->request($url, 'POST', JSON::encode($payload), $this->headers);

        return [
            'response'    => $response,
            'payload'     => $payload,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    public function generateText($messages, $data, $apiKey)
    {
        $contents = [];
        foreach ($messages as $msg) {
            $contents[] = [
                'role'  => $msg['role'],
                'parts' => [['text' => $msg['value']]]
            ];
        }

        $payload = [
            'contents' => $contents
        ];
        $payload = $this->setFeaturePayload($payload, $data);
        $url = self::BASE_URL . "{$data['model']}:generateContent?key={$apiKey}";

        $response = $this->http->request($url, 'POST', JSON::encode($payload), $this->headers);

        return [
            'response'    => $response,
            'payload'     => $payload,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    public function generateImage($data, $apiKey, $model, $safetySettings)
    {
        $model = preg_replace('#^models/#', '', (string) $model);
        $model = self::RETIRED_MODEL_IDS[$model] ?? $model;

        // The dropdown lists whatever image models the key can reach, so any id is
        // passed through and Google reports a bad one. Imagen is the exception: it
        // spoke `:predict`, and Google shut every Imagen model down on 2026-08-17,
        // so nodes still saved against one get told to re-pick instead.
        if ($model === '' || str_starts_with($model, 'imagen-')) {
            return [
                'response' => [
                    // translators: %s: the model id saved on the node.
                    \sprintf(__('"%s" is not a supported image model. Open the node and pick the model again.', 'bit-pi'), $model)
                ],
                'payload'     => ['model' => $model],
                'status_code' => 422,
            ];
        }

        $parts = [
            ['text' => $data['prompt']],
        ];

        $referenceImage = $this->referenceImagePart($data['referenceImages'] ?? null);

        if ($referenceImage) {
            $parts[] = $referenceImage;
        }

        $payload = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => $this->imageGenerationConfig($data),
        ];

        if (!empty($safetySettings)) {
            $payload['safetySettings'] = $safetySettings;
        }

        $header = array_merge(
            $this->headers,
            [
                'x-goog-api-key' => $apiKey,
            ]
        );

        $url = self::BASE_URL . 'models/' . $model . ':generateContent';

        $response = $this->http->request($url, 'POST', JSON::encode($payload), $header);

        return [
            'response'    => $response,
            'payload'     => $payload,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * The tuning values the node left set, plus the modality that makes this an
     * image request rather than a text one.
     *
     * @param mixed $data
     *
     * @return array
     */
    private function imageGenerationConfig($data)
    {
        $settings = $data['generationConfig'] ?? [];

        $config = [];

        if (isset($settings['temperature'])) {
            $config['temperature'] = (float) $settings['temperature'];
        }

        if (isset($settings['topK'])) {
            $config['topK'] = (int) $settings['topK'];
        }

        if (isset($settings['topP'])) {
            $config['topP'] = (float) $settings['topP'];
        }

        if (isset($settings['seed'])) {
            $config['seed'] = (int) $settings['seed'];
        }

        $config['responseModalities'] = ['IMAGE'];

        return $config;
    }

    /**
     * Fetch a reference image and hand it back as an inline part.
     *
     * Anything that is not a readable public http(s) image — a malformed url, an
     * address inside the server's own network, a read that fails — leaves the
     * prompt to stand on its own rather than failing the whole generation.
     *
     * @param mixed $url
     *
     * @return null|array
     */
    private function referenceImagePart($url)
    {
        if (
            !filter_var($url, FILTER_VALIDATE_URL)
            || (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://'))
        ) {
            return;
        }

        $ip = gethostbyname(wp_parse_url($url, PHP_URL_HOST));

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return;
        }

        $imageData = @file_get_contents(
            $url,
            false,
            stream_context_create(
                [
                    'http' => [
                        'follow_location' => 0,
                        'timeout'         => 5
                    ]
                ]
            )
        );

        if ($imageData === false) {
            return;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);

        return [
            'inline_data' => [
                'mime_type' => $finfo->buffer($imageData),
                'data'      => base64_encode($imageData)
            ]
        ];
    }

    private function setFeaturePayload($payload, $data)
    {
        if (!empty($data['max_token'])) {
            $payload['generationConfig']['maxOutputTokens'] = (int) $data['max_token'];
        }
        if (!empty($data['temperature'])) {
            $payload['generationConfig']['temperature'] = (float) $data['temperature'];
        }
        if (!empty($data['topP'])) {
            $payload['generationConfig']['topP'] = (float) $data['topP'];
        }
        if (!empty($data['topK'])) {
            $payload['generationConfig']['topK'] = (int) $data['topK'];
        }
        if (!empty($data['stop_sequence'])) {
            $payload['generationConfig']['stopSequences'] = [$data['stop_sequence']];
        }

        return $payload;
    }
}
