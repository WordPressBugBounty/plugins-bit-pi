<?php

namespace BitApps\Pi\src\Integrations\Brevo\helpers;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}

class BrevoActionHelper
{
    public static function handleConditions($emailList, $id)
    {
        if (!empty($emailList)) {
            return array_map(
                function ($item) use ($id) {
                    return $item[$id];
                },
                $emailList
            );
        }
    }

    public static function handleBooleanParameter($data)
    {
        unset($data['advance-feature']);

        return array_map(
            function ($value) {
                return $value === 'true' ? true
                   : ($value === 'false' ? false : $value);
            },
            $data
        );
    }
}
