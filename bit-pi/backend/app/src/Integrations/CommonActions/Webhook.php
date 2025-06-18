<?php

namespace BitApps\Pi\src\Integrations\CommonActions;

use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\Helpers\MixInputHandler;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!\defined('ABSPATH')) {
    exit;
}


class Webhook implements ActionInterface
{
    protected $nodeInfoProvider;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $machineConfig = $this->nodeInfoProvider->getFieldMapConfigs();

        $queryParams = $this->nodeInfoProvider->getFieldMapRepeaters('repeaters.query_params.value', false);

        $bodyParams = $this->nodeInfoProvider->getFieldMapRepeaters('repeaters.body.value', false);

        $headers = $this->nodeInfoProvider->getFieldMapRepeaters('repeaters.headers.value', false);

        $connectionId = $machineConfig['connection-id']['value'];

        $method = $machineConfig['method']['value'];

        $contentType = $machineConfig['content_type']['value'];

        $uuid = null;

        if ($contentType === 'multipart/form-data') {
            $uuid = uniqid();

            $contentType = "multipart/form-data; boundary={$uuid}";
        }

        $bodyParams = ApiRequestHelper::prepareRequestBody($machineConfig['content_type']['value'], $bodyParams, $uuid);

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

        $http = new HttpClient();

        $response = $http->request($url, $method, $bodyParams, $headers);

        if (\gettype($bodyParams) === 'string') {
            $bodyParams = [$bodyParams];
        }

        return Utility::formatResponseData($http->getResponseCode(), $bodyParams, $response);
    }
}
