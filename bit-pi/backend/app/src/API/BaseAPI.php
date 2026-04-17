<?php

namespace BitApps\Pi\src\API;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\src\Authorization\AbstractBaseAuthorization;
use BitApps\Pi\src\Authorization\ApiKey\ApiKeyAuthorization;
use BitApps\Pi\src\Integrations\CommonActions\ApiRequestHelper;

abstract class BaseAPI
{
    protected AbstractBaseAuthorization $authorization;

    protected HttpClient $http;

    protected string $baseUrl = '';

    protected string $contentType = '';

    public function __construct(AbstractBaseAuthorization $authorization, string $baseUrl = '', string $contentType = '')
    {
        $this->http = new HttpClient();

        $this->authorization = $authorization;

        $this->setBaseUrl($baseUrl);

        $this->setContentType($contentType);

        $this->prepareAuthorization();
    }

    public function getHttpClient(): HttpClient
    {
        return $this->http;
    }

    public function setBaseUrl(string $baseUrl): void
    {
        $this->http->setBaseUri($baseUrl);
        $this->baseUrl = $baseUrl;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function setContentType(string $contentType): void
    {
        $this->contentType = $contentType;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function setPayload($data): void
    {
        switch ($this->contentType) {
            case ContentType::URL_ENCODED:
                $this->http->setFormParams($data);

                break;

            case ContentType::JSON:
                $this->http->setJson($data);

                break;

            case ContentType::FORM_DATA:
                $this->http->setMultipart($data);

                break;

            case ContentType::HTML:
            case ContentType::XML:
            case ContentType::PLAIN_TEXT:
                $this->http->setContentType($this->contentType);
                $this->http->setBody(ApiRequestHelper::prepareRequestBody($this->contentType, $data, null));

                break;

            default:
                $this->http->setBody($data);
        }
    }

    public function request(string $url, string $method, $data = [])
    {
        $url = $this->baseUrl . $url;
        $this->http->setPayload($data);
        $payload = $this->http->getPreparedPayload();
        $headers = $this->http->getHeaders();
        $options = $this->http->getOptions();

        return $this->http->request($url, $method, $payload, $headers, $options);
    }

    public function get(string $url)
    {
        return $this->http->get($url);
    }

    public function post(string $url)
    {
        return $this->http->post($url);
    }

    public function put(string $url)
    {
        return $this->http->put($url);
    }

    public function delete(string $url)
    {
        return $this->http->delete($url);
    }

    public function patch(string $url)
    {
        return $this->http->patch($url);
    }

    private function prepareAuthorization(): void
    {
        if ($this->authorization instanceof ApiKeyAuthorization) {
            $accessToken = $this->authorization->setAuthHeadersOrParams();
        } else {
            $accessToken = $this->authorization->getAccessToken();
        }

        if (\is_string($accessToken)) {
            $this->http->setHeader('Authorization', $accessToken);
        } elseif (\is_array($accessToken)) {
            $authLocation = $accessToken['authLocation'] ?? null;

            $authData = $accessToken['data'] ?? [];

            if ($authLocation === 'header') {
                foreach ($authData as $key => $value) {
                    $this->http->setHeader($key, $value);
                }
            } elseif ($authLocation === 'query_params') {
                foreach ($authData as $key => $value) {
                    $this->http->setQueryParam($key, $value);
                }
            }
        }
    }
}
