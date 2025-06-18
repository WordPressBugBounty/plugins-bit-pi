<?php

namespace BitApps\Pi\src\Integrations\EmailOctopus;

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;

if (!\defined('ABSPATH')) {
    exit;
}


final class EmailOctopusService
{
    private $baseUrl;

    private $connectionId;

    /**
     * JotFormService constructor.
     *
     * @param mixed $connectionId
     * @param mixed $baseUrl
     */
    public function __construct($baseUrl, $connectionId)
    {
        $this->baseUrl = $baseUrl;
        $this->connectionId = $connectionId;
    }

    public function addContact($emailOctopusFields, $listId, $data)
    {
        $checkConfigs = $this->checkConfigs(
            [
                'connection' => $this->connectionId,
                'list'       => $listId,
                'field_map'  => $emailOctopusFields
            ]
        );

        if (isset($checkConfigs['isEmpty']) && $checkConfigs['isEmpty']) {
            return ['response' => $checkConfigs['message'], 'payload' => [], 'status_code' => 400];
        }

        if (isset($data['overrideExisting']) && $data['overrideExisting'] === 'yes') {
            $contact = $this->getContact($emailOctopusFields['EmailAddress'] ?? null, $listId);
        } else {
            $contact = false;
        }

        $apiEndpoint = $this->baseUrl . $listId . '/contacts' . ($contact ? '/' . $contact['id'] : '');
        $bodyParams = $this->formatBodyParams($emailOctopusFields, $data, $contact);

        $http = new HttpClient();

        $response = $http->request(
            $apiEndpoint,
            $contact ? 'PUT' : 'POST',
            JSON::encode($bodyParams),
            [
                'Authorization' => 'Bearer ' . $this->getApiKey(),
                'Content-Type'  => 'application/json'
            ]
        );

        return ['response' => $response, 'payload' => $bodyParams, 'status_code' => $http->getResponseCode()];
    }

    /**
     * Check if the configs are empty.
     *
     * @param array $configs ['key' => 'config']
     *
     * @return bool
     */
    private function checkConfigs(array $configs)
    {
        foreach ($configs as $key => $config) {
            if (empty($config)) {
                $message = __('No', 'bit-pi') . ' '
                . str_replace('_', ' ', $key) . ' '
                . __('is selected!', 'bit-pi');

                return ['isEmpty' => true, 'message' => $message];
            }
        }
    }

    private function formatBodyParams($emailOctopusFields, $data, $contact)
    {
        $result = [];

        if (!empty($emailOctopusFields)) {
            foreach ($emailOctopusFields as $key => $value) {
                if (empty($value)) {
                    continue;
                }

                if ($key === 'EmailAddress') {
                    $result['email_address'] = $value;
                } else {
                    $result['fields'][$key] = $value;
                }
            }
        }

        $tags = !empty($data['tags']) && \is_array($data['tags']) ? $data['tags'] : [];

        if ($contact) {
            $previousTags = $contact['tags'];
            $mergedTags = array_merge($tags, $previousTags);

            foreach ($mergedTags as $tag) {
                $result['tags'][$tag] = \in_array($tag, $tags);
            }
        } else {
            $result['tags'] = $tags;
        }

        if (!empty($data['status'])) {
            $result['status'] = $data['status'];
        }

        return $result;
    }

    private function getContact($email, $listId)
    {
        if (empty($email)) {
            return false;
        }

        $md5Email = md5($email);

        $http = new HttpClient();

        $response = $http->request(
            $this->baseUrl . $listId . '/contacts/' . $md5Email,
            'GET',
            [],
            [
                'Authorization' => 'Bearer ' . $this->getApiKey(),
            ]
        );

        if (\is_object($response) && property_exists($response, 'email_address')) {
            return (array) $response;
        }

        return false;
    }

    private function getApiKey()
    {
        $apiKeyAuthorization = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::API_KEY,
            $this->connectionId
        );

        return $apiKeyAuthorization->getAccessToken();
    }
}
