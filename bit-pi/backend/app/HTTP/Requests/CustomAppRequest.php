<?php

namespace BitApps\Pi\HTTP\Requests;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;

class CustomAppRequest extends Request
{
    public function rules()
    {
        return [
            'name'        => ['required', 'string', 'sanitize:text'],
            'color'       => ['required', 'string', 'sanitize:text'],
            'description' => ['nullable', 'string', 'sanitize:text'],
            'logo'        => ['nullable', 'string', 'sanitize:url'],
            'status'      => ['nullable', 'boolean'],
        ];
    }
}
