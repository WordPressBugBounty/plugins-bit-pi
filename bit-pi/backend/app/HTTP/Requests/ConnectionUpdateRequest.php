<?php

namespace BitApps\Pi\HTTP\Requests;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;

class ConnectionUpdateRequest extends Request
{
    public function rules()
    {
        return [
            'connection'      => ['required', 'integer'],
            'connection_name' => ['required', 'string', 'sanitize:text', 'max:255'],
        ];
    }
}
