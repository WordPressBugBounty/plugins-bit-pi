<?php

namespace BitApps\Pi\src\Authorization\Bearer;

use BitApps\Pi\Helpers\Hash;
use BitApps\Pi\src\Authorization\AbstractBaseAuthorization;

if (!defined('ABSPATH')) {
    exit;
}


class BearerTokenAuthorization extends AbstractBaseAuthorization
{
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

        $authDetails = $connection->auth_details;

        if (!isset($authDetails->token)) {
            return [
                'error'   => true,
                'message' => 'access token field is missing'
            ];
        }

        $encryptKeys = explode(',', $connection->encrypt_keys);

        $token = $authDetails->token;

        if (\in_array('token', $encryptKeys)) {
            $token = Hash::decrypt($token);
        }

        return 'Bearer ' . $token;
    }
}
