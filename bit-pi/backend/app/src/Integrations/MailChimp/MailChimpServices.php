<?php

namespace BitApps\Pi\src\Integrations\MailChimp;

if (!\defined('ABSPATH')) {
    exit;
}


final class MailChimpServices
{
    private $http;

    private $dataCenter;

    /**
     * MailChimpServices Constructor.
     *
     * @param mixed $httpClient
     * @param mixed $dataCenter
     */
    public function __construct($httpClient, $dataCenter)
    {
        $this->http = $httpClient;
        $this->dataCenter = $dataCenter;
    }

    /**
     * Create MailChimp Subscriber.
     *
     * @param array $data
     * @param array $additionalData
     * @param array $addressFieldMapData
     *
     * @return collection
     */
    public function createSubscriber($data, $addressFieldMapData, $additionalData)
    {
        if (empty($data['Email'])) {
            return ['response' => 'Email is required', 'payload' => $data, 'status_code' => 422];
        }

        $module = $additionalData['selectModule'];
        $listId = $additionalData['selectAudience'];

        $apiResponses = [];
        $payLoads = [];

        if (empty($module) || $module == 'add_a_member_to_an_audience') {
            $requestData = self::generateFieldMap($data, $addressFieldMapData, $additionalData);
            $response = $this->insertRecord($listId, wp_json_encode($requestData));
            $apiResponses['addContact'] = $response;
            $payLoads['addContact'] = $requestData;

            if (!empty($additionalData['updateSubscriber']) && $additionalData['updateSubscriber'] === 'yes' && !empty($response->title) && $response->title === 'Member Exists') {
                $contactEmail = $data['Email'];
                $foundContact = $this->isSubscriberExist($listId, $contactEmail);

                if (isset($foundContact->exact_matches->members) && \count($foundContact->exact_matches->members)) {
                    $contactId = $foundContact->exact_matches->members[0]->id;
                    $apiResponses['updateContact'] = $this->updateRecord($listId, $contactId, wp_json_encode($requestData));
                    $payLoads['updateContact'] = $requestData;
                }
            }
        } elseif ($module == 'add_tag_to_a_member' || $module == 'remove_tag_from_a_member') {
            $payLoads['addOrRemoveTag'] = $additionalData['selectTags'];
            $apiResponses['addOrRemoveTag'] = $this->addRemoveTag($module, $data['Email'], $additionalData['selectAudience'], $additionalData['selectTags']);
        }

        return ['response' => $apiResponses, 'payload' => $payLoads, 'status_code' => $this->http->getResponseCode()];
    }

    /**
     * Insert Record.
     *
     * @param string $listId
     * @param mixed  $data
     *
     * @return collection
     */
    public function insertRecord($listId, $data)
    {
        $insertRecordEndpoint = self::baseURL() . "/lists/{$listId}/members";

        return $this->http->request($insertRecordEndpoint, 'POST', $data);
    }

    /**
     * Update Record.
     *
     * @param string $listId
     * @param string $contactId
     * @param mixed  $data
     *
     * @return collection
     */
    public function updateRecord($listId, $contactId, $data)
    {
        $insertRecordEndpoint = self::baseURL() . "/lists/{$listId}/members/{$contactId}";

        return $this->http->request($insertRecordEndpoint, 'PUT', $data);
    }

    /**
     * Check if subscriber exists.
     *
     * @param string $listId
     * @param string $email
     *
     * @return collection
     */
    public function isSubscriberExist($listId, $email)
    {
        $apiEndpoints = self::baseURL() . "/search-members?query={$email}&list_id={$listId}";

        return $this->http->request($apiEndpoints, 'GET', []);
    }

    /**
     * Add Or Remove Tag To A Member.
     *
     * @param string $module
     * @param string $email
     * @param string $listId
     * @param array  $tags
     *
     * @return collection
     */
    public function addRemoveTag($module, $email, $listId, $tags)
    {
        $requestTags = [];
        $isActive = $module == 'add_tag_to_a_member';
        $subscriberHash = md5(strtolower(trim($email)));

        foreach ($tags as $value) {
            $requestTags['tags'][] = ['name' => $value, 'status' => $isActive ? 'active' : 'inactive'];
        }

        $apiEndpoints = $this->baseURL() . "/lists/{$listId}/members/{$subscriberHash}/tags";

        return $this->http->request($apiEndpoints, 'POST', wp_json_encode($requestTags));
    }

    /**
     * Format Field Map Data For MailChimp.
     *
     * @param array $fieldMap
     * @param array $addressFieldMapData
     * @param array $additionalData
     *
     * @return collection
     */
    public function generateFieldMap($fieldMap, $addressFieldMapData, $additionalData)
    {
        $fieldData = [];
        $mergeFields = [];

        foreach ($fieldMap as $field => $value) {
            if (!empty($field)) {
                if ($field === 'Email') {
                    $fieldData['email_address'] = $value;
                } elseif ($field === 'BIRTHDAY') {
                    $date = $value;
                    $mergeFields[$field] = date('m/d', strtotime($date));
                } else {
                    $mergeFields[$field] = $value;
                }
            }
        }

        $doubleOptIn = !empty($additionalData['addDoubleOptIn']) && $additionalData['addDoubleOptIn'] === 'yes';

        $fieldData['merge_fields'] = (object) $mergeFields;
        $fieldData['tags'] = empty($additionalData['selectTags']) ? [] : $additionalData['selectTags'];
        $fieldData['status'] = $doubleOptIn ? 'pending' : 'subscribed';
        $fieldData['double_optin'] = $doubleOptIn;

        if (!empty($additionalData['addAddressField'])) {
            $addressFields = [];

            foreach ($addressFieldMapData as $field => $value) {
                if (!empty($field)) {
                    $addressFields[$field] = $value;
                }
            }

            $fieldData['merge_fields']->ADDRESS = (object) $addressFields;
        }

        return $fieldData;
    }

    /**
     * MailChimp Api Endpoint.
     */
    public function baseURL()
    {
        return 'https://' . $this->dataCenter . '.api.mailchimp.com/3.0';
    }
}
