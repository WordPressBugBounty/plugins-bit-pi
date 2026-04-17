<?php

namespace BitApps\Pi\src\Integrations\CommonActions;

use BitApps\Pi\Model\Connection;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use SimpleXMLElement;

if (!defined('ABSPATH')) {
    exit;
}


class ApiRequestHelper
{
    public static function getAccessToken($connectionId)
    {
        $connection = Connection::select(['auth_details', 'encrypt_keys', 'auth_type'])->findOne(['id' => $connectionId]);

        if ($connection && $connection->auth_details) {
            if ($connection->auth_type === AuthorizationType::API_KEY) {
                return AuthorizationFactory::getAuthorizationHandler(
                    $connection->auth_type,
                    $connectionId
                )->setAuthHeadersOrParams();
            }

            return AuthorizationFactory::getAuthorizationHandler(
                $connection->auth_type,
                $connectionId
            )->getAccessToken();
        }
    }

    public static function prepareRequestBody($contentType, $bodyParams, $boundary)
    {
        switch ($contentType) {
            case 'application/x-www-form-urlencoded':
                return http_build_query($bodyParams);

            case 'application/json':
                return wp_json_encode($bodyParams);

            case 'application/xml':
                return (new self())->arrayToXml($bodyParams, new SimpleXMLElement('<root/>'));

            case 'multipart/form-data':
                return (new self())->generateMultipartFormData($bodyParams, $boundary);

            case 'text/plain':
                $body = '';

                foreach ($bodyParams as $key => $value) {
                    $body .= "{$key}: {$value}\n";
                }

                return rtrim($body);

            case 'text/html':
                $body = '<html><body>';

                foreach ($bodyParams as $key => $value) {
                    $body .= "<p><strong>{$key}:</strong> {$value}</p>";
                }

                return $body . '</body></html>';
        }
    }

    private function generateMultipartFormData($params, $boundary)
    {
        $body = '';

        foreach ($params as $key => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$key}\"\r\n\r\n";
            $body .= "{$value}\r\n";
        }

        return $body . "--{$boundary}--";
    }

    private function arrayToXml($bodyParams, $xml)
    {
        foreach ($bodyParams as $key => $value) {
            if (\is_array($value)) {
                $this->arrayToXml($value, $xml->addChild($key));
            } else {
                $xml->addChild($key, $value);
            }
        }

        return $xml->asXML();
    }
}
