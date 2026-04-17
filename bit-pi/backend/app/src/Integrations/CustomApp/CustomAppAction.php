<?php

namespace BitApps\Pi\src\Integrations\CustomApp;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\Model\CustomMachine;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Integrations\CommonActions\ApiRequestHelper;
use BitApps\Pi\src\Interfaces\ActionInterface;

class CustomAppAction implements ActionInterface
{
    protected $nodeInfoProvider;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $machine = $this->getMachine($this->nodeInfoProvider->getMachineSlug());

        if (isset($machine['status']) && $machine['status'] === 'error') {
            return $machine;
        }

        $machineDetails = $machine->config;

        $fieldMappedData = $this->nodeInfoProvider->getFieldMapData();

        $queryParams = $this->getQueryParams($fieldMappedData, $machineDetails['query_params'] ?? []);

        $bodyParams = $this->getBodyParams($fieldMappedData, $machineDetails['body'] ?? []);

        $headers = $this->getHeaders($fieldMappedData, $machineDetails['headers'] ?? []);

        $contentType = $machineDetails['content_type'];

        $uuid = null;

        if ($contentType === 'multipart/form-data') {
            $uuid = uniqid();

            $contentType = "multipart/form-data; boundary={$uuid}";
        }

        if (isset($machineDetails['is_body_json_enable'])) {
            $requestBody = $this->replaceVariableToFieldValue($fieldMappedData, $machineDetails['body_json'] ?? '');
        } else {
            $requestBody = ApiRequestHelper::prepareRequestBody($machineDetails['content_type'], $bodyParams, $uuid);
        }

        if (isset($machine->config['is_auth_enabled']) && !\is_null($machine->config['is_auth_enabled'])) {
            $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');

            $accessToken = ApiRequestHelper::getAccessToken($connectionId);

            if (\is_string($accessToken)) {
                $headers['Authorization'] = $accessToken;
            } elseif (\is_array($accessToken)) {
                $authLocation = $accessToken['authLocation'] ?? null;

                $authData = $accessToken['data'] ?? [];

                if ($authLocation === 'header') {
                    $headers = array_merge($headers, $authData);
                } elseif ($authLocation === 'query_params') {
                    $queryParams = array_merge($queryParams, $authData);
                }
            }
        }

        $headers['Content-Type'] = $contentType;

        $url = $machineDetails['url'];

        if ($queryParams) {
            $url .= (strpos($url, '?') === false) ? '?' : '&';
            $url .= http_build_query($queryParams);
        }

        $url = $this->replaceVariableToFieldValue($fieldMappedData, $url);

        if ($machineDetails['method'] === 'GET') {
            $requestBody = null;
        }

        $http = new HttpClient();

        $response = $http->request($url, $machineDetails['method'], $requestBody, $headers);

        return Utility::formatResponseData($http->getResponseCode(), $requestBody, $response);
    }

    private function getQueryParams($fieldMappedData, $queryParamKeys)
    {
        $queryParams = [];

        if (!$queryParamKeys) {
            return [];
        }

        if (!$fieldMappedData) {
            return [];
        }

        foreach ($queryParamKeys as $queryParam) {
            if (isset($fieldMappedData[$queryParam['key']])) {
                $queryParams[$queryParam['key']] = $fieldMappedData[$queryParam['key']];
            }
        }

        return $queryParams;
    }

    private function getBodyParams($fieldMappedData, $bodyParamKeys)
    {
        $bodyParams = [];

        if (!$bodyParamKeys) {
            return [];
        }

        if (!$fieldMappedData) {
            return [];
        }

        foreach ($bodyParamKeys as $bodyParam) {
            if (isset($fieldMappedData[$bodyParam['key']])) {
                $bodyParams[$bodyParam['key']] = $fieldMappedData[$bodyParam['key']];
            }
        }

        return $bodyParams;
    }

    private function getHeaders($fieldMappedData, $headerKeys)
    {
        $headers = [];

        if (!$headerKeys) {
            return [];
        }

        if (!$fieldMappedData) {
            return [];
        }

        foreach ($headerKeys as $headerKey) {
            if (isset($fieldMappedData[$headerKey['key']])) {
                $headers[$headerKey['key']] = $fieldMappedData[$headerKey['key']];
            }
        }

        return $headers;
    }

    private function getMachine($machineSlug)
    {
        $machine = CustomMachine::select(['config', 'connection_id'])->findOne(['slug' => $machineSlug, 'status' => 1]);

        if (!$machine) {
            return [
                'status'   => 'error',
                'messages' => 'Action module is disable or deleted',
                'output'   => [],
                'input'    => [],
            ];
        }

        return $machine;
    }

    private function replaceVariableToFieldValue($fieldData, $textWithPlaceholders)
    {
        return preg_replace_callback(
            '/{{(.*?)}}/',
            function ($matches) use ($fieldData) {
                $fieldKey = $matches[1];

                if (!isset($fieldData[$fieldKey])) {
                    return '';
                }

                return preg_replace('/\s+/', ' ', $fieldData[$fieldKey]);
            },
            $textWithPlaceholders
        );
    }
}
