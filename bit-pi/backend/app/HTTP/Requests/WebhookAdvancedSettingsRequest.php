<?php

namespace BitApps\Pi\HTTP\Requests;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;

class WebhookAdvancedSettingsRequest extends Request
{
    public function rules()
    {
        return [
            'webhook'             => ['required', 'integer'],
            'ip_restrictions'     => ['nullable', 'array'],
            'ip_restrictions.*'   => ['nullable', 'string', 'sanitize:text'],
            'include_headers'     => ['nullable', 'boolean'],
            'include_http_method' => ['nullable', 'boolean'],
            'json_pass_through'   => ['nullable', 'boolean'],
        ];
    }
}
