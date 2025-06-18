<?php

namespace BitApps\Pi\src\Integrations\MailBluster;

use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;

if (!\defined('ABSPATH')) {
    exit;
}


final class MailBlusterService
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

    public function addLead($mailBlusterFields, $data)
    {
        $apiKeyAuthorization = AuthorizationFactory::getAuthorizationHandler(AuthorizationType::API_KEY, $this->connectionId);
        $bodyParams = $this->formatBodyParams($mailBlusterFields, $data);
        $http = new HttpClient();

        $response = $http->request(
            $this->baseUrl . 'leads',
            'POST',
            $bodyParams,
            ['Authorization' => $apiKeyAuthorization->getAccessToken()]
        );

        return ['response' => $response, 'payload' => $bodyParams, 'status_code' => $http->getResponseCode()];
    }

    private function formatBodyParams($mailBlusterFields, $data)
    {
        $staticFieldsKeys = ['email', 'firstName', 'lastName', 'timezone', 'ipAddress'];
        $result = [];
        $customFieldsData = [];

        if (!empty($mailBlusterFields)) {
            foreach ($mailBlusterFields as $key => $value) {
                if (\in_array($key, $staticFieldsKeys)) {
                    $result[$key] = $value;
                } else {
                    $customFieldsData[$key] = $value;
                }
            }
        }

        if ($customFieldsData !== []) {
            $result['fields'] = (object) $customFieldsData;
        }

        if (!empty($data)) {
            if (isset($data['overrideExisting']) && $data['overrideExisting'] === 'yes') {
                $result['overrideExisting'] = true;
            }

            if (isset($data['doubleOptIn']) && $data['doubleOptIn'] === 'yes') {
                $result['doubleOptIn'] = true;
            }

            if (isset($data['subscriptionStatus']) && $data['subscriptionStatus'] === 'subscribed') {
                $result['subscribed'] = true;
            } else {
                $result['subscribed'] = false;
            }

            if (isset($data['tags']) && !empty($data['tags']) && \is_array($data['tags'])) {
                $result['tags'] = $data['tags'];
            }
        }

        return $result;
    }
}
