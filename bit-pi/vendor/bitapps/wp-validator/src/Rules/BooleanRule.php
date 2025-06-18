<?php

namespace BitApps\Pi\Deps\BitApps\WPValidator\Rules;

use BitApps\Pi\Deps\BitApps\WPValidator\Rule;

class BooleanRule extends Rule
{
    private $message = 'The :attribute must be a boolean';

    public function validate($value): bool
    {
        return \in_array($value, [true, false, 'true', 'false', 1, 0, '0', '1'], true);
    }

    public function message()
    {
        return $this->message;
    }
}
