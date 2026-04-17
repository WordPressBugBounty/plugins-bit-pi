<?php

namespace BitApps\Pi\src\Integrations\ElevenLabs;

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;

if (!defined('ABSPATH')) {
    exit;
}

class ElevenLabsService
{
    private const BASE_URL = 'https://api.elevenlabs.io/v1';

    private HttpClient $http;

    /**
     * ElevenLabsService constructor.
     */
    public function __construct(HttpClient $httpClient)
    {
        $this->http = $httpClient;
    }

    /**
     * Convert text to speech.
     *
     * @param array  $fieldMapData
     * @param string $voiceId
     *
     * @return array
     */
    public function textToSpeech($fieldMapData, $voiceId)
    {
        $endPoint = self::BASE_URL . '/text-to-speech/' . urlencode($voiceId);
        $response = $this->http->request($endPoint, 'POST', JSON::encode($fieldMapData));

        return [
            'response'    => $response,
            'payload'     => $fieldMapData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * List all available voices.
     *
     * @return array
     */
    public function listVoices()
    {
        $endPoint = self::BASE_URL . '/voices';
        $response = $this->http->request($endPoint, 'GET', []);

        return [
            'response'    => $response,
            'payload'     => [],
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Get details of a specific voice.
     *
     * @param string $voiceId
     *
     * @return array
     */
    public function getVoice($voiceId)
    {
        $endPoint = self::BASE_URL . '/voices/' . urlencode($voiceId);
        $response = $this->http->request($endPoint, 'GET', []);

        return [
            'response'    => $response,
            'payload'     => ['voice_id' => $voiceId],
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Delete a specific voice.
     *
     * @param string $voiceId
     *
     * @return array
     */
    public function deleteVoice($voiceId)
    {
        $endPoint = self::BASE_URL . '/voices/' . urlencode($voiceId);
        $response = $this->http->request($endPoint, 'DELETE', []);

        return [
            'response'    => $response,
            'payload'     => ['voice_id' => $voiceId],
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Convert speech to text.
     *
     * @param array $fieldMapData
     * @param mixed $modelId
     *fieldMapData
     *
     * @return array
     */
    public function speechToText($modelId, $fieldMapData)
    {
        $fieldMapData['model_id'] = 'scribe_v2';
        $endPoint = self::BASE_URL . '/speech-to-text';
        $boundary = uniqid('boundary_');
        $body = '';

        foreach ($fieldMapData as $key => $value) {
            if (\is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="' . $key . '"' . "\r\n\r\n";
            $body .= $value . "\r\n";
        }

        $body .= '--' . $boundary . '--';

        $headers = $this->http->getHeaders();
        $headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;

        $response = $this->http->request($endPoint, 'POST', $body, $headers);

        return [
            'response'    => $response,
            'payload'     => array_merge(['model_id' => $modelId], $fieldMapData),
            'status_code' => $this->http->getResponseCode(),
        ];
    }

    /**
     * Create a conversational agent.
     *
     * @param array  $fieldMapData
     *
     * @return array
     */
    public function createAgent($fieldMapData)
    {
        $endPoint = self::BASE_URL . '/convai/agents/create';
        $response = $this->http->request($endPoint, 'POST', JSON::encode($fieldMapData));

        return [
            'response'    => $response,
            'payload'     => $fieldMapData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Get details of a specific agent.
     *
     * @param string $agentId
     *
     * @return array
     */
    public function getAgent($agentId)
    {
        $endPoint = self::BASE_URL . '/convai/agents/' . urlencode($agentId);
        $response = $this->http->request($endPoint, 'GET', []);

        return [
            'response'    => $response,
            'payload'     => ['agent_id' => $agentId],
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Delete a specific agent.
     *
     * @param string $agentId
     *
     * @return array
     */
    public function deleteAgent($agentId)
    {
        $endPoint = self::BASE_URL . '/convai/agents/' . urlencode($agentId);
        $response = $this->http->request($endPoint, 'DELETE', []);

        return [
            'response'    => $response,
            'payload'     => ['agent_id' => $agentId],
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * List all available agents.
     *
     * @param mixed $params
     *
     * @return array
     */
    public function listAgents($params)
    {
        $endPoint = self::BASE_URL . '/convai/agents';

        if (!empty($params)) {
            $endPoint = add_query_arg($params, $endPoint);
        }

        $response = $this->http->request($endPoint, 'GET', []);

        return [
            'response'    => $response,
            'payload'     => [],
            'status_code' => $this->http->getResponseCode()
        ];
    }
}
