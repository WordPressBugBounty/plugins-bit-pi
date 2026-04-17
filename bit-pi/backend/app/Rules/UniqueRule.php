<?php

namespace BitApps\Pi\Rules;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPValidator\Rule;

class UniqueRule extends Rule
{
    private $message = 'The :attribute must be unique.';

    private $model;

    private $ignoreId;

    private $column;

    public function __construct($model, $column, $customMessage = null)
    {
        $this->model = $model;
        $this->column = $column;

        if ($customMessage) {
            $this->message = $customMessage;
        }
    }

    public function validate($value)
    {
        /* TODO:
            Fix: This role is not receiving the validated value from the previous role validation of the same row (if there are any).
            Remove the sanitize_text_field() from here.
        */

        $query = $this->model::where($this->column, sanitize_text_field($value));

        if ($this->ignoreId) {
            $query->where('id', '!=', $this->ignoreId);
        }

        $result = $query->count();

        return \is_null($result) || $result == 0;
    }

    public function ignore($ignoreId)
    {
        $this->ignoreId = $ignoreId;

        return $this;
    }

    public function message()
    {
        return $this->message;
    }
}
