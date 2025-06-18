<?php

namespace BitApps\Pi\src\Integrations\GetGist;

use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;

if (!\defined('ABSPATH')) {
    exit;
}


final class GetGistService
{
    private $baseUrl;

    private $connectionId;

    private $http;

    private $tokenAuthorization;

    private $headers;

    /**
     * GetGistService constructor.
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
            AuthorizationType::BEARER_TOKEN,
            $this->connectionId
        );
        $this->headers = [
            'Authorization' => $this->tokenAuthorization->getAccessToken(),
            'Content-Type'  => 'application/json'
        ];
    }

    public function checkContactExists($email)
    {
        $params = http_build_query(
            [
                'email' => $email
            ]
        );

        return $this->http->request(
            $this->baseUrl . '/contacts?' . $params,
            'GET',
            [],
            $this->headers
        );
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
     * @param mixed $tagId
     * @param mixed $overRideExistingEmail
     *
     * @return collection
     */
    public function createContact($taskData, $tagId, $overRideExistingEmail)
    {
        $staticFieldsKeys = [
            'name',
            'email',
            'phone',
            'gender',
            'country',
            'city',
            'company_name',
            'industry',
            'job_title',
            'last_name',
            'postal_code',
            'state',
        ];

        $processedData = $this->generateFieldMap($taskData, $staticFieldsKeys);

        if (!isset($processedData['email'])) {
            return ['response' => __('Required field Email is empty', 'bit-pi'), 'payload' => $processedData, 'status_code' => 400];
        }

        // Check Override Email

        if ($overRideExistingEmail === 'true') {
            $contactExists = $this->checkContactExists($processedData['email']);

            if (isset($contactExists->contact)) {
                $processedData['user_id'] = $contactExists->contact->id;
            } else {
                return ['response' => __('Email already exists', 'bit-pi'), 'payload' => $processedData, 'status_code' => 400];
            }
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
