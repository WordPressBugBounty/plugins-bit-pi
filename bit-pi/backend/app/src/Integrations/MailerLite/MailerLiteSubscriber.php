<?php

namespace BitApps\Pi\src\Integrations\MailerLite;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}


final class MailerLiteSubscriber
{
    private $http;

    private $baseUrl;

    /**
     * MailerLiteSubscriber constructor.
     *
     * @param mixed $httpClient
     * @param mixed $baseUrl
     */
    public function __construct($httpClient, $baseUrl)
    {
        $this->http = $httpClient;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Create mailerlite subscriber.
     *
     * @param array $data
     * @param mixed $additionalData
     *
     * @return collection
     */
    public function createSubscriber($data, $additionalData)
    {
        if (empty($data['email'])) {
            return ['response' => 'Email is required', 'payload' => $data, 'status_code' => 422];
        }

        $isSubscriberExist = $this->isSubscriberExist($data['email']);

        if ($isSubscriberExist && isset($additionalData['updateSubscriber']) && $additionalData['updateSubscriber'] == 'no') {
            return ['response' => 'Subscriber already exist', 'payload' => '', 'status_code' => 422];
        }

        $requestParams = [
            'email'  => $data['email'],
            'status' => $additionalData['subscriberType'] ?? 'active',
        ];

        foreach ($data as $key => $value) {
            if ($key !== 'email') {
                $requestParams['fields'][$key] = $value;
            }
        }

        $createSubscriberRes = $this->http->request($this->baseUrl . 'subscribers', 'POST', $requestParams);

        $apiResponses = [];
        $apiResponses[$isSubscriberExist ? 'updateSubscriber' : 'createSubscriber'] = $createSubscriberRes;
        $payLoads = [];
        $payLoads[$isSubscriberExist ? 'updateSubscriber' : 'createSubscriber'] = $requestParams;

        if (isset($createSubscriberRes->data->id, $additionalData['groups'])) {
            $addSubscriberToGroupsRes = $this->addSubscriberToGroups($createSubscriberRes->data->id, $additionalData['groups']);
            $apiResponses['addSubscriberToGroups'] = $addSubscriberToGroupsRes;
            $payLoads['addSubscriberToGroups'] = $additionalData['groups'];
        }

        return ['response' => $apiResponses, 'payload' => $payLoads, 'status_code' => $this->http->getResponseCode()];
    }

    /**
     * Add subscriber to groups.
     *
     * @param string $subscriberId
     * @param array  $groupIds
     *
     * @return collection
     */
    public function addSubscriberToGroups($subscriberId, $groupIds)
    {
        foreach ($groupIds as $groupId) {
            $apiEndpoints = $this->baseUrl . "subscribers/{$subscriberId}/groups/{$groupId}";
            $response = $this->http->request($apiEndpoints, 'POST', []);
        }

        return $response;
    }

    /**
     * Check if subscriber exists.
     *
     * @param string $email
     *
     * @return collection
     */
    public function isSubscriberExist($email)
    {
        $apiEndpoints = $this->baseUrl . "subscribers/{$email}";
        $apiResponse = $this->http->request($apiEndpoints, 'GET', []);

        return !property_exists($apiResponse, 'error') && !(isset($apiResponse->message) && $apiResponse->message === 'Resource not found.');
    }
}
