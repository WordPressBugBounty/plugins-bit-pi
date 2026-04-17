<?php

namespace BitApps\Pi\src\Integrations\WhatsApp;

use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;

if (!defined('ABSPATH')) {
    exit;
}
class WhatsAppService
{
    private const BASE_URL = 'https://graph.facebook.com/v20.0/';

    private $http;

    private $headers;

    /**
     * WhatsApp constructor.
     *
     * @param array  $headers
     */
    public function __construct($headers)
    {
        $this->http = new HttpClient();
        $this->headers = $headers;
    }

    /**
     * Send Template Message.
     *
     * @param mixed $fieldMapData
     * @param mixed $phoneNumberId
     *
     * @return array
     */
    public function sendTemplateMessage($fieldMapData, $phoneNumberId)
    {
        unset($fieldMapData['phoneNumberId']);

        if (isset($fieldMapData['template']['name'])) {
            $separatedValues = explode(' ', $fieldMapData['template']['name']);
            $fieldMapData['template']['name'] = $separatedValues[0];
            $fieldMapData['template']['language'] = ['code' => $separatedValues[1] ?? 'en_US'];
            $templateType = $separatedValues[2];
        }

        if ($templateType != 'TEXT') {
            $templateStructuredData = $this->buildTemplateStructure($templateType, $fieldMapData);
            $fieldMapData['template'] = array_merge($fieldMapData['template'], $templateStructuredData['template']);
        }

        unset($fieldMapData['location']);
        $endPoint = self::BASE_URL . $phoneNumberId . '/messages';
        $response = $this->http->request($endPoint, 'POST', $fieldMapData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $fieldMapData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Send Message.
     *
     * @param mixed $fieldMapData
     * @param int $phoneNumberId
     *
     * @return array
     */
    public function sendMessage($fieldMapData, $phoneNumberId)
    {
        unset($fieldMapData['phoneNumberId']);
        $endPoint = self::BASE_URL . $phoneNumberId . '/messages';
        $response = $this->http->request($endPoint, 'POST', $fieldMapData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $fieldMapData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Send Image.
     *
     * @param mixed $fieldMapData
     * @param int $phoneNumberId
     *
     * @return array
     */
    public function sendImage($fieldMapData, $phoneNumberId)
    {
        unset($fieldMapData['phoneNumberId']);
        $endPoint = self::BASE_URL . $phoneNumberId . '/messages';
        $response = $this->http->request($endPoint, 'POST', $fieldMapData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $fieldMapData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Send Video.
     *
     * @param mixed $fieldMapData
     * @param int $phoneNumberId
     *
     * @return array
     */
    public function sendVideo($fieldMapData, $phoneNumberId)
    {
        unset($fieldMapData['phoneNumberId']);
        $endPoint = self::BASE_URL . $phoneNumberId . '/messages';
        $response = $this->http->request($endPoint, 'POST', $fieldMapData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $fieldMapData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Send Document.
     *
     * @param mixed $fieldMapData
     * @param int $phoneNumberId
     *
     * @return array
     */
    public function sendDocument($fieldMapData, $phoneNumberId)
    {
        unset($fieldMapData['phoneNumberId']);
        $endPoint = self::BASE_URL . $phoneNumberId . '/messages';
        $response = $this->http->request($endPoint, 'POST', $fieldMapData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $fieldMapData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Send Audio.
     *
     * @param mixed $fieldMapData
     * @param int $phoneNumberId
     *
     * @return array
     */
    public function sendAudio($fieldMapData, $phoneNumberId)
    {
        unset($fieldMapData['phoneNumberId']);
        $endPoint = self::BASE_URL . $phoneNumberId . '/messages';
        $response = $this->http->request($endPoint, 'POST', $fieldMapData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $fieldMapData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Send Location.
     *
     * @param mixed $fieldMapData
     * @param int $phoneNumberId
     *
     * @return array
     */
    public function sendLocation($fieldMapData, $phoneNumberId)
    {
        unset($fieldMapData['phoneNumberId']);
        $endPoint = self::BASE_URL . $phoneNumberId . '/messages';
        $fieldMapData['location']['longitude'] = (float) $fieldMapData['location']['longitude'];
        $fieldMapData['location']['latitude'] = (float) $fieldMapData['location']['latitude'];
        $response = $this->http->request($endPoint, 'POST', $fieldMapData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $fieldMapData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    public function buildTemplateStructure(string $type, array $data): array
    {
        $payload = [
            'template' => [
                'components' => [
                    [
                        'type'       => 'header',
                        'parameters' => [],
                    ],
                ],
            ],
        ];


        $param = ['type' => strtolower($type)];

        switch ($type) {
            case 'IMAGE':
                $param['image'] = ['link' => $data['link']];

                break;

            case 'VIDEO':
                $param['video'] = ['link' => $data['link']];

                break;

            case 'DOCUMENT':
                $param['document'] = ['link' => $data['link']];

                break;

            case 'LOCATION':
                $param['location'] = [
                    'latitude'  => (float) $data['location']['latitude'],
                    'longitude' => (float) $data['location']['longitude'],
                    'name'      => $data['location']['name'],
                    'address'   => $data['location']['address']
                ];

                break;
        }

        $payload['template']['components'][0]['parameters'][] = $param;

        return $payload;
    }
}
