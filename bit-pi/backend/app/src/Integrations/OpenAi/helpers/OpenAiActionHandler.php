<?php

namespace BitApps\Pi\src\Integrations\OpenAi\helpers;

use BitApps\Pi\Config;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\src\Integrations\OpenAi\OpenAiService;

class OpenAiActionHandler
{
    /**
     * Prepare a multimodal Chat Completions payload for the Analyze Image action.
     */
    public static function prepareImageAnalysisPayload(array $fieldMapData, array $imageInputs): array
    {
        $content = [
            [
                'type' => 'text',
                'text' => $fieldMapData['prompt'],
            ],
        ];

        foreach ($imageInputs as $imageInput) {
            $imageUrl = $imageInput['image-url'] ?? '';

            if (!\is_string($imageUrl)) {
                continue;
            }

            $imageUrl = trim($imageUrl);

            if ($imageUrl === '') {
                continue;
            }

            $detail = $imageInput['detail'] ?? 'auto';
            if (!\in_array($detail, ['auto', 'low', 'high'], true)) {
                $detail = 'auto';
            }

            $content[] = [
                'type'      => 'image_url',
                'image_url' => [
                    'url'    => $imageUrl,
                    'detail' => $detail,
                ],
            ];
        }

        $fieldMapData['response_format'] = '{"type":"text"}';
        $fieldMapData['messages'] = [
            [
                'role'    => 'user',
                'content' => $content,
            ],
        ];

        // max_tokens caps the single combined response, so drop it unless a usable limit was set
        $maxTokens = $fieldMapData['max_tokens'] ?? null;

        if (is_numeric($maxTokens) && (int) $maxTokens > 0) {
            $fieldMapData['max_tokens'] = (int) $maxTokens;
        } else {
            unset($fieldMapData['max_tokens']);
        }

        return $fieldMapData;
    }

    public static function handleConditions($fieldMapData, $stopSequence, $messageList, $inputFormat, $inputText, $optionalFields)
    {
        if ($fieldMapData['advance-feature'] === 'true') {
            $result = array_map(
                function ($item) {
                    return $item['stop-sequences-id'];
                },
                $stopSequence
            );

            $stop['stop'] = $result;
            $messages['messages'] = $messageList;
            $fieldMapData = array_merge($fieldMapData, $messages, $stop);
        }

        if (isset($fieldMapData['content'])) {
            $messages = [
                ['role' => 'system', 'content' => $fieldMapData['prompt']],
                ['role' => 'user', 'content' => $fieldMapData['content']],
            ];
            $fieldMapData['messages'] = $messages;
        }

        if ($inputFormat === 'array_of_text') {
            $input['input'] = $inputText;
            $fieldMapData = array_merge($fieldMapData, $input);
        }

        if (!empty($messageList)) {
            $messages['messages'] = $messageList;
            $fieldMapData = array_merge($fieldMapData, $messages);
        }

        if (!empty($optionalFields)) {
            $optionalField['optionalFields'] = $optionalFields;
            $optionalField = array_column($optionalFields, 'value', 'optional-fields');
            $fieldMapData = array_merge($fieldMapData, $optionalField);
        }
        $numberOfImage = 'n';
        if (\array_key_exists($numberOfImage, $fieldMapData)) {
            $fieldMapData[$numberOfImage] = (int) $fieldMapData[$numberOfImage];
        } elseif (\array_key_exists('max_tokens', $fieldMapData)) {
            $fieldMapData['max_tokens'] = (int) $fieldMapData['max_tokens'];
        } elseif (\array_key_exists('max_completion_tokens', $fieldMapData)) {
            $fieldMapData['max_completion_tokens'] = (int) $fieldMapData['max_completion_tokens'];
        }

        return $fieldMapData;
    }

    public static function handleMemoryContext($memoryData, $response, $memoryKey, $contextLength)
    {
        $key = 'chatgpt-' . $memoryKey;

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

    public static function castFieldsIfExist(array $data)
    {
        $casts = [
            'logit_bias'          => 'array',
            'logprobs'            => 'bool',
            'metadata'            => 'array',
            'modalities'          => 'array',
            'parallel_tool_calls' => 'bool',
            'prediction'          => 'array',
            'service_tier'        => 'string',
            'store'               => 'bool',
            'stream'              => 'bool',
            'stream_options'      => 'array',
            'tool_choice'         => 'string',
            'tools'               => 'array',
            'top_logprobs'        => 'int',
            'user'                => 'string',
            'web_search_options'  => 'array',
        ];
        foreach ($casts as $key => $type) {
            if (\array_key_exists($key, $data) && \gettype($data[$key]) !== $type) {
                settype($data[$key], $type);
            }
        }

        return $data;
    }

    public static function executeAction(
        $machineSlug,
        OpenAiService $openAiService,
        $batchLimit,
        $batchId,
        $fieldMapData,
        $configs,
        $memoryKey,
        $contextLength
    ) {
        switch ($machineSlug) {
            case 'analyzeImage':
                return $openAiService->createCompletion($fieldMapData);

            case 'createCompletion':
                return $openAiService->createCompletion(
                    $fieldMapData,
                    $configs['memory-key-switch']['value'],
                    $memoryKey,
                    $contextLength
                );

            case 'listBatches':
                return $openAiService->listBatches($batchLimit);

            case 'getBatch':
                return $openAiService->getBatch($batchId);

            case 'generateImage':
                return $openAiService->generateImage($fieldMapData);

            case 'generateAnAudio':
                return $openAiService->generateAudio($fieldMapData);

            case 'createModeration':
                return $openAiService->createModeration($fieldMapData);

            case 'textToStructuredData':
                return $openAiService->createCompletion($fieldMapData);
        }
    }
}
