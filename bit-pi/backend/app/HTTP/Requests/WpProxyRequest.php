<?php

namespace BitApps\Pi\HTTP\Requests;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;

class WpProxyRequest extends Request
{
    public function rules()
    {
        return [
            'path'        => ['required', 'string', 'sanitize:text'],
            'method'      => ['required', 'string', 'sanitize:text'],
            'headers'     => ['nullable', 'array'],
            'queryParams' => ['nullable', 'array'],
            'bodyParams'  => ['nullable', 'array'],
        ];
    }
}
