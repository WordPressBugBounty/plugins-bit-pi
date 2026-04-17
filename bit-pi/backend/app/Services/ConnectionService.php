<?php

namespace BitApps\Pi\Services;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

class ConnectionService
{
    public static function modifyAuthDetails($authDetails, $appSlug)
    {
        if ($appSlug === 'salesforce') {
            if (!isset($authDetails['expires_in'])) {
                $authDetails['expires_in'] = 7200;
            }
        }

        return $authDetails;
    }
}
