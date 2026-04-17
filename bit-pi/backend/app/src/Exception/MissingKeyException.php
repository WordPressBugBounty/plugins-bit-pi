<?php

namespace BitApps\Pi\src\Exception;

use Exception;

if (!defined('ABSPATH')) {
    exit;
}


class MissingKeyException extends Exception
{
    public function __construct($code = 0, ?Exception $previous = null)
    {
        $message = 'Error: Missing required key {input, output, status} in the array.';

        parent::__construct($message, $code, $previous);
    }
}
