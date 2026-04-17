<?php

namespace BitApps\Pi\HTTP\Requests;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;

class FlowUpdateRequest extends Request
{
    public function rules()
    {
        return [
            'id'   => ['required', 'integer'],
            'flow' => ['required', 'array'],
            'tag'  => ['nullable', 'array'],
        ];
    }
}
