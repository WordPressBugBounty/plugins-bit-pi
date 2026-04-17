<?php

namespace BitApps\Pi\HTTP\Requests;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;

class ProxyRequest extends Request
{
    public function rules()
    {
        return [
            'url'         => ['required', 'string', 'url', 'sanitize:url'],
            'method'      => ['required', 'string', 'sanitize:text'],
            'headers'     => ['nullable', 'array'],
            'queryParams' => ['nullable', 'array'],
            'bodyParams'  => ['nullable', 'array'],
        ];
    }
}
