<?php

namespace BitApps\Pi\src\Integrations\Selzy;

use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;

if (!\defined('ABSPATH')) {
    exit;
}


final class SelzyService
{
    private $baseUrl;

    private $connectionId;

    private $http;

    private $tokenAuthorization;

    private $headers = [
        'content-type' => 'application/json'
    ];

    /**
     * SelzyService constructor.
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
    }

    /**
     * Process Data.
     *
     * @param array $contactData
     *
     * @return array
     */
    public function generateFieldMap($contactData)
    {
        $processedData = [];

        foreach ($contactData as $data) {
            $processedData['fields'][$data['column']] = $data['value'];
        }

        return $processedData;
    }

    /**
     * Create New Contact.
     *
     * @param mixed $contactData
     * @param mixed $listId
     * @param mixed $tagId
     * @param mixed $overrideExisting
     * @param mixed $doubleOptin
     *
     * @return collection
     */
    public function createContact($contactData, $tagId, $listId, $overrideExisting, $doubleOptin)
    {
        $processedData = $this->generateFieldMap($contactData);

        if (isset($listId) || $listId !== null) {
            $processedData['list_ids'] = implode(',', $listId);
        } else {
            return ['response' => __('Required field list is empty', 'bit-pi'), 'payload' => $processedData, 'status_code' => 400];
        }

        if (isset($overrideExisting)) {
            $processedData['overwrite'] = $overrideExisting;
        }

        if (isset($doubleOptin) && $doubleOptin === 'true') {
            $processedData['double_optin'] = 4;
        }

        if (isset($tagId) || $tagId !== null) {
            $processedData['tags'] = $tagId;
        }

        $processedData['api_key'] = $this->tokenAuthorization->getAccessToken();
        $processedData['format'] = 'json';

        $queryParams = http_build_query($processedData, '', '&', PHP_QUERY_RFC3986);
        $queryParamsAfterReplace = str_replace('%5B', '[', $queryParams);
        $queryParamsFinal = str_replace('%5D', ']', $queryParamsAfterReplace);

        $url = $this->baseUrl . '/subscribe?' . $queryParamsFinal;

        $response = $this->http->request(
            $url,
            'GET',
            [],
            $this->headers
        );

        return ['response' => $response, 'payload' => $processedData, 'status_code' => $this->http->getResponseCode()];
    }
}
