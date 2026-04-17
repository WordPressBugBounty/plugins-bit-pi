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

    private const IMAGEN_MODELS = [
        'imagen-4.0-generate-preview-06-06',
        'imagen-4.0-ultra-generate-preview-06-06',
        'imagen-4.0-fast-generate-001',
    ];

    private const NANO_BANANA_MODELS = [
        'gemini-2.5-flash-image-preview',
        'gemini-3-pro-image-preview',
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
        if (\in_array($model, self::IMAGEN_MODELS, true)) {
            $payload = [
                'instances' => [
                    [
                        'prompt' => $data['prompt'],
                    ],
                ],
                'parameters' => [
                    'aspectRatio' => $data['aspectRatio'] ?? '1:1',
                ],
            ];
        } elseif (\in_array($model, self::NANO_BANANA_MODELS, true)) {
            $parts = [
                ['text' => $data['prompt']],
            ];

            if (!empty($data['referenceImages'])) {
                $url = $data['referenceImages'];
                if (
                    filter_var($url, FILTER_VALIDATE_URL)
                    && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'))
                ) {
                    $ip = gethostbyname(wp_parse_url($url, PHP_URL_HOST));
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
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
                        if ($imageData !== false) {
                            $base64Image = base64_encode($imageData);
                            $finfo = new finfo(FILEINFO_MIME_TYPE);
                            $mimeType = $finfo->buffer($imageData);
                            $parts[] = [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data'      => $base64Image
                                ]
                            ];
                        }
                    }
                }
            }

            $generationConfig = [];

            if (!empty($data['generationConfig'])) {
                if (isset($data['generationConfig']['temperature'])) {
                    $generationConfig['temperature'] = (float) $data['generationConfig']['temperature'];
                }
                if (isset($data['generationConfig']['topK'])) {
                    $generationConfig['topK'] = (int) $data['generationConfig']['topK'];
                }
                if (isset($data['generationConfig']['topP'])) {
                    $generationConfig['topP'] = (float) $data['generationConfig']['topP'];
                }
                if (isset($data['generationConfig']['seed'])) {
                    $generationConfig['seed'] = (int) $data['generationConfig']['seed'];
                }
            }

            $generationConfig['responseModalities'] = ['IMAGE'];

            $payload = [
                'contents' => [
                    [
                        'role'  => 'user',
                        'parts' => $parts,
                    ],
                ],
                'generationConfig' => $generationConfig,
            ];

            if (!empty($safetySettings)) {
                $payload['safetySettings'] = $safetySettings;
            }
        }

        $header = array_merge(
            $this->headers,
            [
                'x-goog-api-key' => $apiKey,
            ]
        );

        if (\in_array($model, self::IMAGEN_MODELS, true)) {
            $url = self::BASE_URL . 'models/' . $model . ':predict';
        } else {
            $url = self::BASE_URL . 'models/' . $model . ':generateContent';
        }

        $response = $this->http->request($url, 'POST', JSON::encode($payload), $header);

        return [
            'response'    => $response,
            'payload'     => $payload,
            'status_code' => $this->http->getResponseCode()
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
