<?php

namespace BitApps\Pi\src\Integrations\GoogleSheet\Helpers;

use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


class Common
{
    public const AUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public static function excelColumnToIndex($column)
    {
        $column = strtoupper($column);

        $length = \strlen($column);

        $index = 0;

        for ($i = 0; $i < $length; ++$i) {
            $index *= 26;
            $index += \ord($column[$i]) - \ord('A') + 1;
        }

        return $index - 1;
    }

    public static function getAuthorizationHeader($connectionId): array
    {
        $accessToken = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::OAUTH2,
            $connectionId
        )->setRefreshTokenUrl(self::AUTH_TOKEN_URL)->getAccessToken();

        if (!\is_string($accessToken)) {
            return $accessToken;
        }

        return [
            'Authorization' => $accessToken,
            'Content-Type'  => 'application/json'
        ];
    }
}
