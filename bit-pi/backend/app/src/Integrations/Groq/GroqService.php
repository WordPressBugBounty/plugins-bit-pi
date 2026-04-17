<?php

namespace BitApps\Pi\src\Integrations\Groq;

use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Integrations\AIIntegrationHelper;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

class GroqService
{
    private const BASE_URL = 'https://api.groq.com/openai/v1/';

    private HttpClient $http;

    public function __construct(HttpClient $httpClient)
    {
        $this->http = $httpClient;
    }

    /**
     * Create Chat Completion.
     *
     * @param array  $messages
     * @param array  $data
     * @param bool   $isMemoryEnabled
     * @param string $memoryKey
     * @param int    $contextLength
     *
     * @return array
     */
    public function createChatCompletion($messages, $data, $isMemoryEnabled = false, $memoryKey = '', $contextLength = 0)
    {
        $hasMemoryKey = ($isMemoryEnabled === true && !empty($memoryKey));
        $memoryData = [];

        if ($hasMemoryKey) {
            $key = 'groq-' . $memoryKey;
            $memoryData = Config::getOption($key, []);

            if (!empty($memoryData)) {
                $messages = array_merge($memoryData, $messages);
            }
        }

        $data['messages'] = $messages;
        $data = AIIntegrationHelper::castPayloadTypes($data);
        $response = $this->http->request(self::BASE_URL . 'chat/completions', 'POST', JSON::encode($data));

        if ($hasMemoryKey) {
            self::handleMemoryContext($memoryData, $response, $memoryKey, $contextLength);
        }

        return [
            'response'    => $response,
            'payload'     => $data,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    // /**
    //  * Transcribe Audio.
    //  *
    //  * @param array $infoData
    //  *
    //  * @return array
    //  */
    // public function transcribeAudio($infoData)
    // {
    //     $filePath = Utility::getFilePath($infoData['file']);
    //     if (empty($filePath)) {
    //         return [
    //             'response'    => 'File is required',
    //             'payload'     => $infoData,
    //             'status_code' => 400
    //         ];
    //     }

    //     if (\is_array($filePath)) {
    //         $filePath = $filePath[0];
    //     }

    //     $payload = [
    //         'model' => !empty($infoData['model']) ? $infoData['model'] : 'whisper-large-v3',
    //     ];

    //     if (!empty($infoData['language'])) {
    //         $payload['language'] = $infoData['language'];
    //     }

    //     if (!empty($infoData['prompt'])) {
    //         $payload['prompt'] = $infoData['prompt'];
    //     }

    //     if (isset($infoData['temperature'])) {
    //         $payload['temperature'] = \floatval($infoData['temperature']);
    //     }

    //     if (!empty($infoData['response_format'])) {
    //         $payload['response_format'] = $infoData['response_format'];
    //     }

    //     $this->http->setMultipart([]);
    //     $this->http->addFile('file', $filePath);

    //     foreach ($payload as $key => $value) {
    //         $this->http->addPostField($key, $value);
    //     }

    //     $response = $this->http->request(self::BASE_URL . 'audio/transcriptions', 'POST', []);

    //     return [
    //         'response'    => $response,
    //         'payload'     => $payload,
    //         'status_code' => $this->http->getResponseCode()
    //     ];
    // }

    // /**
    //  * Translate Audio.
    //  *
    //  * @param array $infoData
    //  *
    //  * @return array
    //  */
    // public function translateAudio($infoData)
    // {
    //     $filePath = Utility::getFilePath($infoData['file']);
    //     if (empty($filePath)) {
    //         return [
    //             'response'    => 'File is required',
    //             'payload'     => $infoData,
    //             'status_code' => 400
    //         ];
    //     }

    //     if (\is_array($filePath)) {
    //         $filePath = $filePath[0];
    //     }

    //     $payload = [
    //         'model' => !empty($infoData['model']) ? $infoData['model'] : 'whisper-large-v3',
    //     ];

    //     if (!empty($infoData['prompt'])) {
    //         $payload['prompt'] = $infoData['prompt'];
    //     }

    //     if (isset($infoData['temperature'])) {
    //         $payload['temperature'] = \floatval($infoData['temperature']);
    //     }

    //     if (!empty($infoData['response_format'])) {
    //         $payload['response_format'] = $infoData['response_format'];
    //     }

    //     $this->http->setMultipart([]);
    //     $this->http->addFile('file', $filePath);

    //     foreach ($payload as $key => $value) {
    //         $this->http->addPostField($key, $value);
    //     }

    //     $response = $this->http->request(self::BASE_URL . 'audio/translations', 'POST', []);

    //     return [
    //         'response'    => $response,
    //         'payload'     => $payload,
    //         'status_code' => $this->http->getResponseCode()
    //     ];
    // }

    // /**
    //  * Analyze Image.
    //  *
    //  * @param array $infoData
    //  *
    //  * @return array
    //  */
    // public function analyzeImage($infoData)
    // {
    //     $payload = [
    //         'model'       => !empty($infoData['model']) ? $infoData['model'] : 'llama-3.2-11b-vision-preview',
    //         'temperature' => isset($infoData['temperature']) ? \floatval($infoData['temperature']) : 1,
    //         'max_tokens'  => isset($infoData['max_tokens']) ? \intval($infoData['max_tokens']) : 1024,
    //         'messages'    => [
    //             [
    //                 'role'    => 'user',
    //                 'content' => [
    //                     [
    //                         'type' => 'text',
    //                         'text' => !empty($infoData['prompt']) ? $infoData['prompt'] : 'What is in this image?',
    //                     ],
    //                     [
    //                         'type'      => 'image_url',
    //                         'image_url' => [
    //                             'url' => $infoData['image_url'],
    //                         ],
    //                     ],
    //                 ],
    //             ],
    //         ],
    //     ];

    //     if (isset($infoData['top_p'])) {
    //         $payload['top_p'] = \floatval($infoData['top_p']);
    //     }

    //     if (isset($infoData['frequency_penalty'])) {
    //         $payload['frequency_penalty'] = \floatval($infoData['frequency_penalty']);
    //     }

    //     if (isset($infoData['seed'])) {
    //         $payload['seed'] = \intval($infoData['seed']);
    //     }

    //     $response = $this->http->request(self::BASE_URL . 'chat/completions', 'POST', JSON::encode($payload));

    //     return [
    //         'response'    => $response,
    //         'payload'     => $payload,
    //         'status_code' => $this->http->getResponseCode()
    //     ];
    // }

    public static function handleMemoryContext(array $memoryData, object $response, string $memoryKey, int $contextLength): void
    {
        $key = 'groq-' . $memoryKey;

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

    // TODO: Uncomment and use if needed in future
    // private function formatPayloadStructure($data)
    // {
    //     if (isset($data['max_tokens']) && !\is_array($data['max_tokens'])) {
    //         $data['max_tokens'] = (int) $data['max_tokens'];
    //     }
    //     if (isset($data['temperature']) && !\is_array($data['temperature'])) {
    //         $data['temperature'] = (float) $data['temperature'];
    //     }
    //     if (isset($data['top_p']) && !\is_array($data['top_p'])) {
    //         $data['top_p'] = (float) $data['top_p'];
    //     }
    //     if (isset($data['seed']) && !\is_array($data['seed'])) {
    //         $data['seed'] = (int) $data['seed'];
    //     }
    //     if (isset($data['frequency_penalty']) && !\is_array($data['frequency_penalty'])) {
    //         $data['frequency_penalty'] = (float) $data['frequency_penalty'];
    //     }

    //     return $data;
    // }
}
