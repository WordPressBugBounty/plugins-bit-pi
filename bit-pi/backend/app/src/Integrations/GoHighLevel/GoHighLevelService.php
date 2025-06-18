<?php

namespace BitApps\Pi\src\Integrations\GoHighLevel;

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;

if (!\defined('ABSPATH')) {
    exit;
}


final class GoHighLevelService
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

    public function addContact($goHighLevelFields, $data)
    {
        $checkConfigs = $this->checkConfigs(
            [
                'connection' => $this->connectionId,
                'field_map'  => $goHighLevelFields
            ]
        );

        if (isset($checkConfigs['isEmpty']) && $checkConfigs['isEmpty']) {
            return ['response' => $checkConfigs['message'], 'payload' => [], 'status_code' => 400];
        }

        $apiEndpoint = $this->baseUrl . 'contacts';
        $bodyParams = $this->formatBodyParams($goHighLevelFields, $data);

        $http = new HttpClient();

        $response = $http->request(
            $apiEndpoint,
            'POST',
            JSON::encode($bodyParams),
            [
                'Authorization' => 'Bearer ' . $this->getApiKey(),
                'Content-Type'  => 'application/json'
            ]
        );

        return ['response' => $response, 'payload' => $bodyParams, 'status_code' => $http->getResponseCode()];
    }

    public function updateContact($contactId, $goHighLevelFields, $data)
    {
        $checkConfigs = $this->checkConfigs(
            [
                'contact'    => $contactId,
                'connection' => $this->connectionId,
                'field_map'  => $goHighLevelFields
            ]
        );

        if (isset($checkConfigs['isEmpty']) && $checkConfigs['isEmpty']) {
            return ['response' => $checkConfigs['message'], 'payload' => [], 'status_code' => 400];
        }

        $apiEndpoint = $this->baseUrl . 'contacts/' . $contactId;
        $bodyParams = $this->formatBodyParams($goHighLevelFields, $data);

        $http = new HttpClient();

        $response = $http->request(
            $apiEndpoint,
            'PUT',
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

    private function formatBodyParams($goHighLevelFields, $data)
    {
        $result = [];

        if (!empty($goHighLevelFields)) {
            foreach ($goHighLevelFields as $key => $value) {
                if (empty($value)) {
                    continue;
                }

                if ($this->isStaticField($key)) {
                    $result[$key] = $value;
                } else {
                    $result['customField'][$key] = $value;
                }
            }
        }

        $result['tags'] = !empty($data['tags']) && \is_array($data['tags']) ? $data['tags'] : [];

        $result['dnd'] = isset($result['dnd']) && ($result['dnd'] == 1 || $result['dnd'] === 'true');

        return $result;
    }

    private function getApiKey()
    {
        $apiKeyAuthorization = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::API_KEY,
            $this->connectionId
        );

        return $apiKeyAuthorization->getAccessToken();
    }

    private function isStaticField(string $fieldKey): bool
    {
        $staticFields = [
            'email',
            'firstName',
            'lastName',
            'name',
            'phone',
            'dateOfBirth',
            'address1',
            'city',
            'state',
            'country',
            'postalCode',
            'companyName',
            'website',
            'dnd',
            'source',
        ];

        return \in_array($fieldKey, $staticFields);
    }
}
