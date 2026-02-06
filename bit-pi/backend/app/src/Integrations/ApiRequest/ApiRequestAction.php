<?php

namespace BitApps\Pi\src\Integrations\ApiRequest;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\Helpers\MixInputHandler;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Integrations\CommonActions\ApiRequestHelper;
use BitApps\Pi\src\Interfaces\ActionInterface;

class ApiRequestAction implements ActionInterface
{
    protected $nodeInfoProvider;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $machineConfig = $this->nodeInfoProvider->getFieldMapConfigs();

        $queryParams = $this->nodeInfoProvider->getFieldMapRepeaters('query-params.value', false);

        $bodyParams = $this->nodeInfoProvider->getFieldMapRepeaters('body.value', false);

        $headers = $this->nodeInfoProvider->getFieldMapRepeaters('headers.value', false);

        $connectionId = $machineConfig['connection-id']['value'];

        $method = $machineConfig['method']['value'];

        $contentType = $machineConfig['content-type']['value'];

        $uuid = null;

        if ($contentType === 'multipart/form-data') {
            $uuid = uniqid();

            $contentType = "multipart/form-data; boundary={$uuid}";
        }

        $bodyParams = ApiRequestHelper::prepareRequestBody($machineConfig['content-type']['value'], $bodyParams, $uuid);

        if (!\is_null($connectionId)) {
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

        $url = MixInputHandler::replaceMixTagValue($this->nodeInfoProvider->getFieldMapConfigs('url.value'));

        if ($queryParams) {
            $url .= (strpos($url, '?') === false) ? '?' : '&';
            $url .= http_build_query($queryParams);
        }

        if ($method === 'GET') {
            $bodyParams = null;
        }

        $isEnableRawBody = $machineConfig['is-enable-raw-body']['value'] ?? false;

        $rawBodyContent = $machineConfig['raw-body-content']['value'] ?? '';

        if ($isEnableRawBody && !empty($rawBodyContent)) {
            $bodyParams = MixInputHandler::replaceMixTagValue($rawBodyContent, 'array');

            $bodyParams = $this->convertArrayToJsonWithProperTypes($bodyParams);
        }

        $http = new HttpClient();

        $response = $http->request(
            $url,
            $method,
            $bodyParams,
            $headers,
            ['sslverify' => true]
        );

        if (\gettype($bodyParams) === 'string') {
            $bodyParams = [$bodyParams];
        }

        $payload = $method === 'GET' ? $queryParams : array_merge($queryParams, $bodyParams);

        $response = \is_array($response) || \is_object($response) ? $response : ['data' => $response];

        return Utility::formatResponseData(
            $http->getResponseCode(),
            $payload,
            $response
        );
    }

    private function convertArrayToJsonWithProperTypes($elements)
    {
        $jsonString = '';

        foreach ($elements as $element) {
            if (\is_bool($element)) {
                $jsonString .= $element ? '{{true}}' : '{{false}}';
            } else {
                $jsonString .= $element;
            }
        }

        return preg_replace('/"\{\{(true|false)\}\}"/', '$1', $jsonString);
    }
}
