<?php

namespace BitApps\Pi\HTTP\Requests;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;

class McpToolsRequest extends Request
{
    public function rules()
    {
        return [
            'serverUrl'       => ['required', 'array'],
            'serverTransport' => ['required', 'string', 'sanitize:text'],
            'connectionId'    => ['nullable', 'integer'],
        ];
    }
}
