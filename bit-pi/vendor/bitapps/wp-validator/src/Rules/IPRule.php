<?php
namespace BitApps\Pi\Deps\BitApps\WPValidator\Rules;

use BitApps\Pi\Deps\BitApps\WPValidator\Rule;

class IPRule extends Rule
{
    private $message = "The :attribute must be a valid IP address";

    public function validate($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    public function message()
    {
        return $this->message;
    }
}
