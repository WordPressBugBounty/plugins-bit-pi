<?php

namespace BitApps\Pi\src\Exception;

use Exception;

if (!\defined('ABSPATH')) {
    exit;
}


class PlatformNotFoundException extends Exception
{
    public function __construct($appSlug, $code = 0, ?Exception $previous = null)
    {
        $message = "Error: Platform {$appSlug} not found. Please ensure that the specified platform is supported and correctly configured";
        parent::__construct($message, $code, $previous);
    }
}
