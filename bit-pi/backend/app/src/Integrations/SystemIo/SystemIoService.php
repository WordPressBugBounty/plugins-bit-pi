<?php

namespace BitApps\Pi\src\Integrations\SystemIo;

use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;

if (!\defined('ABSPATH')) {
    exit;
}


final class SystemIoService
{
    private $baseUrl;

    private $connectionId;

    private $http;

    private $tokenAuthorization;

    private $headers;

    /**
     * SystemIoService constructor.
     *
     * @param mixed $connectionId
     * @param mixed $baseUrl
     */
    public function __construct($baseUrl, $connectionId)
    {
        $this->baseUrl = $baseUrl;
        $this->connectionId = $connectionId;
        $this->http = new HttpClient();

        $this->tokenAuthorization = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::API_KEY,
            $this->connectionId
        );
        $this->headers = [
            'x-api-key'    => $this->tokenAuthorization->getAccessToken(),
            'content-type' => 'application/json'
        ];
    }

    /**
     * Process Data.
     *
     * @param array $taskData
     *
     * @return array
     */
    public function generateFieldMap($taskData)
    {
        $processedData = [];

        foreach ($taskData as $data) {
            if ($data['column'] == 'email') {
                $processedData[$data['column']] = $data['value'];
            } else {
                $processedData['fields'][] = (object) [
                    'slug'  => $data['column'],
                    'value' => $data['value']
                ];
            }
        }

        return $processedData;
    }

    /**
     * Add Tag to a Contact.
     *
     * @param mixed $contactId
     * @param mixed $tagId
     */
    public function addTag($tagId, $contactId)
    {
        $data['tagId'] = (int) $tagId;

        $this->http->request(
            $this->baseUrl . '/contacts/' . $contactId . '/tags',
            'POST',
            wp_json_encode($data),
            $this->headers
        );
    }

    /**
     * Create New Form.
     *
     * @param mixed $taskData
     * @param mixed $tagId
     *
     * @return collection
     */
    public function createContact($taskData, $tagId)
    {
        $processedData = $this->generateFieldMap($taskData);

        if (!(\array_key_exists('email', $processedData))) {
            return ['response' => __('Required field Email is empty', 'bit-pi'), 'payload' => $processedData, 'status_code' => 400];
        }

        $headers = [
            'x-api-key'    => $this->tokenAuthorization->getAccessToken(),
            'content-type' => 'application/json'
        ];

        $response = $this->http->request(
            $this->baseUrl . '/contacts',
            'POST',
            wp_json_encode($processedData),
            $headers
        );

        if (isset($tagId) && !empty($tagId) && isset($response->id)) {
            $this->addTag($tagId, $response->id);
        }

        return ['response' => $response, 'payload' => $processedData, 'status_code' => $this->http->getResponseCode()];
    }
}
