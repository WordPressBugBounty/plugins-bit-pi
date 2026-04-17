<?php

namespace BitApps\Pi\HTTP\Requests;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;

class ConnectionStoreRequest extends Request
{
    public function rules()
    {
        return [
            'app_slug'        => ['required', 'string', 'sanitize:text'],
            'auth_type'       => ['required', 'string', 'sanitize:text'],
            'connection_name' => ['required', 'string', 'sanitize:text'],
            'encrypt_keys'    => ['nullable', 'array'],
            'auth_details'    => ['required', 'array'],
        ];
    }
}
