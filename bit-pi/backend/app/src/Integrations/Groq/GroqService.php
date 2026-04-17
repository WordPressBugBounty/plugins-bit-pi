<?php

namespace BitApps\Pi\src\Integrations\Groq;

use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\src\Integrations\AIIntegrationHelper;
use Exception;

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

    /**
     * Transcribe Audio.
     *
     * @param array $infoData
     *
     * @return array
     */
    public function transcribeAudio($infoData)
    {
        return $this->processAudioRequest('audio/transcriptions', $infoData, ['language', 'prompt', 'temperature', 'response_format']);
    }

    /**
     * Translate Audio.
     *
     * @param array $infoData
     *
     * @return array
     */
    public function translateAudio($infoData)
    {
        return $this->processAudioRequest('audio/translations', $infoData, ['prompt', 'temperature', 'response_format']);
    }

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

    /**
     * Shared handler for audio transcription and translation requests.
     */
    private function processAudioRequest(string $endpoint, array $infoData, array $optionalFields): array
    {
        $boundary = uniqid('boundary_');
        $body = '';

        $textFields = ['model' => !empty($infoData['model']) ? $infoData['model'] : 'whisper-large-v3'];

        foreach ($optionalFields as $field) {
            if (isset($infoData[$field]) && $infoData[$field] !== '') {
                $textFields[$field] = $infoData[$field];
            }
        }

        foreach ($textFields as $name => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $body .= "{$value}\r\n";
        }

        if (empty($infoData['file'])) {
            throw new Exception('Audio file is required for this action.');
        }

        $fileData = $this->resolveFileContent($infoData['file']);
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$fileData['filename']}\"\r\n";
        $body .= "Content-Type: application/octet-stream\r\n\r\n";
        $body .= "{$fileData['content']}\r\n";

        $body .= "--{$boundary}--";

        $headers = $this->http->getHeaders();
        $headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;

        $response = $this->http->request(self::BASE_URL . $endpoint, 'POST', $body, $headers);

        return [
            'response'    => $response,
            'payload'     => $infoData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Resolve file content from remote URL.
     *
     * @throws Exception
     *
     * @return array{content: string, filename: string}
     */
    private function resolveFileContent(string $fileUrl): array
    {
        $response = wp_remote_get($fileUrl, ['timeout' => 60]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $errorMessage = is_wp_error($response) ? $response->get_error_message() : 'HTTP code ' . wp_remote_retrieve_response_code($response);

            throw new Exception('Failed to download audio from URL: ' . $fileUrl . '. Error: ' . $errorMessage);
        }

        $content = wp_remote_retrieve_body($response);

        if (\strlen($content) === 0) {
            throw new Exception('Empty response from URL: ' . $fileUrl);
        }

        $extension = pathinfo(parse_url($fileUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
        $filename = 'downloaded_audio.' . ($extension ?: 'mp3');

        return ['content' => $content, 'filename' => $filename];
    }
}
