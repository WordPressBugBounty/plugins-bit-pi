<?php

namespace BitApps\Pi\src\Integrations\OpenAi;

use BitApps\Pi\Config;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\src\Integrations\AIIntegrationHelper;
use BitApps\Pi\src\Integrations\OpenAi\helpers\OpenAiActionHandler;

class OpenAiService
{
    private $baseUrl;

    private $http;

    private $headers;

    /**
     * OpenAiService constructor.
     *
     * @param string $baseUrl
     * @param array  $headers
     */
    public function __construct($baseUrl, $headers)
    {
        $this->baseUrl = $baseUrl;
        $this->http = new HttpClient();
        $this->headers = $headers;
    }

    /**
     * Create Completion.
     *
     * @param mixed $fieldMapData
     * @param bool $isMemoryEnabled
     * @param string $memoryKey
     * @param int $contextLength
     *
     * @return array
     */
    public function createCompletion($fieldMapData, $isMemoryEnabled = null, $memoryKey = null, $contextLength = null)
    {
        unset($fieldMapData['prompt'], $fieldMapData['content'], $fieldMapData['advance-feature']);
        $endPoint = $this->baseUrl . '/chat/completions';
        $fieldMapData['response_format'] = JSON::decode($fieldMapData['response_format']);
        $hasMemoryKey = ($isMemoryEnabled === true && $memoryKey !== '');

        if ($hasMemoryKey) {
            $key = 'chatgpt-' . $memoryKey;
            $memoryData = Config::getOption($key, []);

            $fieldMapData = array_merge(
                $fieldMapData,
                [
                    'prompt_cache_key' => $key,
                ]
            );

            if (!empty($memoryData)) {
                $fieldMapData['messages'] = array_merge($memoryData, $fieldMapData['messages']);
            }
        }

        $fieldMapData = AIIntegrationHelper::castPayloadTypes($fieldMapData);
        $response = $this->http->request($endPoint, 'POST', JSON::encode($fieldMapData), $this->headers);

        if ($hasMemoryKey) {
            OpenAiActionHandler::handleMemoryContext($memoryData, $response, $memoryKey, $contextLength);
        }

        return [
            'response'    => $response,
            'payload'     => $fieldMapData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Generate Image.
     *
     * @param mixed $generateImageBody
     *
     * @return array
     */
    public function generateImage($generateImageBody)
    {
        if ($generateImageBody['model'] === 'dall-e-2') {
            unset($generateImageBody['sizeForDalle3'], $generateImageBody['quality'], $generateImageBody['style']);
            $generateImageBody['size'] = $generateImageBody['sizeForDalle2'];
            unset($generateImageBody['sizeForDalle2']);
        } elseif ($generateImageBody['model'] === 'dall-e-3') {
            unset($generateImageBody['sizeForDalle2'], $generateImageBody['n'], $generateImageBody['response_format']);
            $generateImageBody['size'] = $generateImageBody['sizeForDalle3'];
            unset($generateImageBody['sizeForDalle3']);
        }


        $endPoint = $this->baseUrl . '/images/generations';
        $response = $this->http->request($endPoint, 'POST', JSON::encode($generateImageBody), $this->headers);

        return [
            'response'    => $response,
            'payload'     => $generateImageBody,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Generate Audio.
     *
     * @param array $fieldMapData
     *
     * @return array
     */
    public function generateAudio($fieldMapData)
    {
        $endPoint = $this->baseUrl . '/audio/speech';
        $audioRequestData = JSON::encode($fieldMapData);
        $response = $this->http->request($endPoint, 'POST', $audioRequestData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $audioRequestData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * List of Batches according to Limit.
     *
     * @param array $batchLimit
     *
     * @return array
     */
    public function listBatches($batchLimit)
    {
        $endPoint = add_query_arg('limit', $batchLimit, $this->baseUrl . '/batches');

        $response = $this->http->request($endPoint, 'GET', [], $this->headers);

        $statusCode = $this->http->getResponseCode();

        return [
            'response'    => $response,
            'payload'     => $batchLimit,
            'status_code' => $statusCode
        ];
    }

    /**
     * Get A Batch.
     *
     * @param mixed $batchId
     *
     * @return array
     */
    public function getBatch($batchId)
    {
        $endPoint = $this->baseUrl . '/batches/' . $batchId;

        $response = $this->http->request($endPoint, 'GET', [], $this->headers);

        $statusCode = $this->http->getResponseCode();

        return [
            'response'    => $response,
            'payload'     => $batchId,
            'status_code' => $statusCode
        ];
    }

    /**
     * Cancel A Batch.
     *
     * @param mixed $batchId
     *
     * @return array
     */
    public function cancelBatch($batchId)
    {
        $endPoint = $this->baseUrl . '/batches/' . $batchId . '/cancel';

        $response = $this->http->request($endPoint, 'POST', [], $this->headers);

        $statusCode = $this->http->getResponseCode();

        return [
            'response'    => $response,
            'payload'     => $batchId,
            'status_code' => $statusCode
        ];
    }

    /**
     * Create Moderation.
     *
     * @param mixed $data
     *
     * @return array
     */
    public function createModeration($data)
    {
        $endPoint = $this->baseUrl . '/moderations';
        $firstIndex = 0;

        if (($data['model'] ?? '') === 'text-moderation-latest') {
            if (!empty($data['input']) && \is_array($data['input'][$firstIndex])) {
                $data['input'] = array_column($data['input'] ?? [], 'text');
            }
        }

        $response = $this->http->request($endPoint, 'POST', JSON::encode($data), $this->headers);

        return [
            'response'    => $response,
            'payload'     => $data,
            'status_code' => $this->http->getResponseCode()
        ];
    }
}
