<?php

namespace BitApps\Pi\src\Integrations\DeepSeek;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Helpers\MixInputHandler;
use BitApps\Pi\src\API\BaseAPI;
use BitApps\Pi\src\Authorization\ApiKey\ApiKeyAuthorization;

class DeepSeekHelper extends BaseAPI
{
    private const BASE_URL = 'https://api.deepseek.com/';

    public function __construct(ApiKeyAuthorization $authorization, $apiKey)
    {
        parent::__construct($authorization, self::BASE_URL, 'application/json');
        $this->http->setHeader('Authorization', 'Bearer ' . $apiKey);
    }

    public function getUserBalance()
    {
        $response = (array) $this->get('user/balance');

        return [
            'response'    => $response,
            'payload'     => [],
            'status_code' => $this->http->getResponseCode()
        ];
    }

    public function listModels()
    {
        $response = (array) $this->get('models');

        return [
            'response'    => $response,
            'payload'     => [],
            'status_code' => $this->http->getResponseCode()
        ];
    }

    public function createChatCompletion($messages, $infoData)
    {
        $payLoad = [
            'model'       => !empty($infoData['model']) ? $infoData['model'] : 'deepseek-chat',
            'temperature' => isset($infoData['temperature']) ? \floatval($infoData['temperature']) : 0.7,
            'max_tokens'  => isset($infoData['max_tokens']) ? \intval($infoData['max_tokens']) : 0
        ];

        $payLoad['messages'] = $messages;
        $this->setPayload($payLoad);

        $response = (array) $this->post('chat/completions');

        return [
            'response'    => $response,
            'payload'     => $payLoad,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    private function getMixInputValue($value)
    {
        if (!empty($value)) {
            return MixInputHandler::replaceMixTagValue($value);
        }

        return $value;
    }
}
