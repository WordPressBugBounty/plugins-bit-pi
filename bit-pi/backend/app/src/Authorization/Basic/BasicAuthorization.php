<?php

namespace BitApps\Pi\src\Authorization\Basic;

use BitApps\Pi\Helpers\Hash;
use BitApps\Pi\src\Authorization\AbstractBaseAuthorization;

if (!defined('ABSPATH')) {
    exit;
}


class BasicAuthorization extends AbstractBaseAuthorization
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

        if (!isset($authDetails->username) || !isset($authDetails->password)) {
            return [
                'error'   => true,
                'message' => 'username or password field is missing'
            ];
        }

        $encryptKeys = explode(',', $connection->encrypt_keys);

        $username = $authDetails->username ?? '';

        $password = $authDetails->password ?? '';

        if (\in_array('username', $encryptKeys)) {
            $username = Hash::decrypt($authDetails->username);
        }

        if (\in_array('password', $encryptKeys)) {
            $password = Hash::decrypt($authDetails->password);
        }

        return 'Basic ' . base64_encode($username . ':' . $password);
    }
}
