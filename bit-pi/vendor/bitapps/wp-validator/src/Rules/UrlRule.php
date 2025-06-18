<?php
namespace BitApps\Pi\Deps\BitApps\WPValidator\Rules;

use BitApps\Pi\Deps\BitApps\WPValidator\Rule;

class UrlRule extends Rule
{
    private $message = "The :attribute format is invalid";

    public function validate($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    public function message()
    {
        return $this->message;
    }
}
