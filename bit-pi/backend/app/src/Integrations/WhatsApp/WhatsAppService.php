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
     * @param array $templateParams placeholder key => mapped value
     *
     * @return array
     */
    public function sendTemplateMessage($fieldMapData, $phoneNumberId, $templateParams = [])
    {
        unset($fieldMapData['phoneNumberId']);
        $templateType = 'TEXT';
        $templateNameIndex = 0;
        $languageCodeIndex = 1;
        $templateTypeIndex = 2;

        if (isset($fieldMapData['template']['name'])) {
            $separatedValues = explode(' ', $fieldMapData['template']['name']);
            $fieldMapData['template']['name'] = $separatedValues[$templateNameIndex] ?? '';
            $fieldMapData['template']['language'] = ['code' => $separatedValues[$languageCodeIndex] ?? 'en_US'];

            if (!empty($separatedValues[$templateTypeIndex]) && $separatedValues[$templateTypeIndex] !== 'undefined') {
                $templateType = $separatedValues[$templateTypeIndex];
            }
        }

        $components = [];

        if ($templateType !== 'TEXT') {
            $templateStructuredData = $this->buildTemplateStructure($templateType, $fieldMapData);
            $components = $templateStructuredData['template']['components'];
        }

        $components = $this->mergeTemplateComponents($components, $this->buildParameterComponents($templateParams));

        if ($components !== []) {
            $fieldMapData['template']['components'] = $components;
        }

        unset($fieldMapData['location'], $fieldMapData['link']);
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

    /**
     * Builds template components out of the mapped placeholders.
     *
     * Placeholder keys are `header.{token}`, `body.{token}` and `button.{index}.{token}`.
     *
     * @param array $templateParams
     *
     * @return array
     */
    private function buildParameterComponents($templateParams)
    {
        if (!\is_array($templateParams) || $templateParams === []) {
            return [];
        }

        $sections = ['header' => [], 'body' => []];
        $buttons = [];

        foreach ($templateParams as $placeholder => $value) {
            $segments = explode('.', (string) $placeholder);
            $section = array_shift($segments);

            if ($section === 'button') {
                $index = array_shift($segments);
                $token = array_shift($segments);

                if ($index !== null && $token !== null) {
                    $buttons[$index][$token] = $value;
                }

                continue;
            }

            if (!isset($sections[$section])) {
                continue;
            }

            $token = array_shift($segments);

            if ($token !== null) {
                $sections[$section][$token] = $value;
            }
        }

        $components = [];

        foreach ($sections as $type => $params) {
            if ($params === []) {
                continue;
            }

            $components[] = ['type' => $type, 'parameters' => $this->buildTextParameters($params)];
        }

        foreach ($buttons as $index => $params) {
            $components[] = [
                'type'       => 'button',
                'sub_type'   => 'url',
                'index'      => (string) $index,
                'parameters' => $this->buildTextParameters($params)
            ];
        }

        return $components;
    }

    /**
     * Converts a token => value map into WhatsApp text parameters,
     * keeping positional placeholders in their numeric order.
     *
     * @param array $params
     *
     * @return array
     */
    private function buildTextParameters($params)
    {
        uksort(
            $params,
            function ($a, $b) {
                if (is_numeric($a) && is_numeric($b)) {
                    return (int) $a <=> (int) $b;
                }

                return strcmp((string) $a, (string) $b);
            }
        );

        $parameters = [];

        foreach ($params as $token => $value) {
            $parameter = ['type' => 'text', 'text' => (string) $value];

            if (!is_numeric($token)) {
                $parameter['parameter_name'] = $token;
            }

            $parameters[] = $parameter;
        }

        return $parameters;
    }

    /**
     * Merges placeholder components into the media/location components,
     * so a media header keeps its own parameters.
     *
     * @param array $components
     * @param array $extraComponents
     *
     * @return array
     */
    private function mergeTemplateComponents($components, $extraComponents)
    {
        foreach ($extraComponents as $extra) {
            $matchedIndex = null;

            foreach ($components as $index => $component) {
                $componentIndex = isset($component['index']) ? $component['index'] : null;
                $extraIndex = isset($extra['index']) ? $extra['index'] : null;

                if ($component['type'] === $extra['type'] && $componentIndex === $extraIndex) {
                    $matchedIndex = $index;

                    break;
                }
            }

            if ($matchedIndex === null) {
                $components[] = $extra;

                continue;
            }

            $components[$matchedIndex]['parameters'] = array_merge(
                $components[$matchedIndex]['parameters'] ?? [],
                $extra['parameters'] ?? []
            );
        }

        return $components;
    }
}
