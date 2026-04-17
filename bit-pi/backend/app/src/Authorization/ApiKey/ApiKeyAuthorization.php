<?php

namespace BitApps\Pi\src\Authorization\ApiKey;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Helpers\Hash;
use BitApps\Pi\src\Authorization\AbstractBaseAuthorization;

class ApiKeyAuthorization extends AbstractBaseAuthorization
{
    private $authDetails;

    public function __construct($connectionId)
    {
        parent::__construct($connectionId);
    }

    public function getAccessToken()
    {
        $connection = $this->getConnection();

        if (!$connection) {
            return [
                'error'   => true,
                'message' => 'Connection not found',
            ];
        }

        $this->authDetails = $connection->auth_details;

        if (!isset($this->authDetails->value)) {
            return [
                'error'   => true,
                'message' => 'token field is missing'
            ];
        }

        $encryptKeys = explode(',', $connection->encrypt_keys);

        $value = $this->authDetails->value;

        if (\in_array('value', $encryptKeys)) {
            return Hash::decrypt($value);
        }

        return $value;
    }

    /**
     * Returns the authorization data as key-value pairs and their intended location.
     *
     * @return array
     */
    public function setAuthHeadersOrParams()
    {
        $accessToken = $this->getAccessToken();

        if (\is_array($accessToken) && isset($accessToken['error']) && $accessToken['error']) {
            return $accessToken;
        }

        return [
            'authLocation' => $this->authDetails->addTo,
            'data'         => [$this->authDetails->key => $accessToken],
        ];
    }
}
