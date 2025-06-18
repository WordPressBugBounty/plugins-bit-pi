<?php
namespace BitApps\Pi\Deps\BitApps\WPValidator\Rules;

use BitApps\Pi\Deps\BitApps\WPValidator\Rule;

class IP4Rule extends Rule
{
    private $message = "The :attribute must be a valid IPv4 address";

    public function validate($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    public function message()
    {
        return $this->message;
    }
}
