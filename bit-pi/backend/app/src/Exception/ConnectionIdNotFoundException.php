<?php

namespace BitApps\Pi\src\Exception;

use Exception;

if (!defined('ABSPATH')) {
    exit;
}


class ConnectionIdNotFoundException extends Exception
{
    public function __construct($connectionId, $code = 0, ?Exception $previous = null)
    {
        $message = "Connection ID '{$connectionId}' not found.";
        parent::__construct($message, $code, $previous);
    }
}
