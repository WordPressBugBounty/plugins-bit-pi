<?php
namespace BitApps\Pi\Deps\BitApps\WPValidator\Rules;

use BitApps\Pi\Deps\BitApps\WPValidator\Rule;

class NullableRule extends Rule
{

    private $message = '';

    public function validate($value): bool
    {
        return true;
    }

    public function message()
    {
        return $this->message;
    }
}
