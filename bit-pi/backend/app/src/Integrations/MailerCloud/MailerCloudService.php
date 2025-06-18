<?php

namespace BitApps\Pi\src\Integrations\MailerCloud;

use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;

if (!\defined('ABSPATH')) {
    exit;
}


final class MailerCloudService
{
    private $baseUrl;

    private $connectionId;

    private $http;

    private $tokenAuthorization;

    private $headers;

    /**
     * MailerCloudService constructor.
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
            'Authorization' => $this->tokenAuthorization->getAccessToken(),
            'content-type'  => 'application/json'
        ];
    }

    /**
     * Process Data.
     *
     * @param array $taskData
     * @param mixed $staticFieldsKeys
     *
     * @return array
     */
    public function generateFieldMap($taskData, $staticFieldsKeys)
    {
        $processedData = [];

        foreach ($taskData as $data) {
            if (\in_array($data['column'], $staticFieldsKeys)) {
                $processedData[$data['column']] = $data['value'];
            } else {
                $processedData['custom_fields'] = (object) [
                    $data['column'] => $data['value'],
                ];
            }
        }

        return $processedData;
    }

    /**
     * Create New Form.
     *
     * @param mixed $taskData
     * @param mixed $listId
     * @param mixed $tagId
     *
     * @return collection
     */
    public function createContact($taskData, $tagId, $listId)
    {
        $staticFieldsKeys = [
            'mailbox_provider',
            'userip',
            'lead_source',
            'salary',
            'job_title',
            'department',
            'company_name',
            'industry',
            'postal_code',
            'phone',
            'state',
            'city',
            'country',
            'last_name',
            'middle_name',
            'name',
            'email',
        ];
        $processedData = $this->generateFieldMap($taskData, $staticFieldsKeys);

        if (isset($listId) || $listId !== null) {
            $processedData['list_id'] = $listId;
        } else {
            return ['response' => __('Required field list is empty', 'bit-pi'), 'payload' => $processedData, 'status_code' => 400];
        }

        if (!(\array_key_exists('email', $processedData))) {
            return ['response' => __('Required field Email is empty', 'bit-pi'), 'payload' => $processedData, 'status_code' => 400];
        }

        if (isset($listId) || !empty($listId)) {
            $processedData['list_id'] = $listId;
        }

        if (isset($tagId) || $tagId !== null) {
            $processedData['tags'] = $tagId;
        }

        $response = $this->http->request(
            $this->baseUrl . '/contacts',
            'POST',
            wp_json_encode($processedData),
            $this->headers
        );

        return ['response' => $response, 'payload' => $processedData, 'status_code' => $this->http->getResponseCode()];
    }
}
