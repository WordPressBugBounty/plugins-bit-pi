<?php

namespace BitApps\Pi\HTTP\Requests;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;

class WebhookAuthenticationRequest extends Request
{
    public function rules()
    {
        return [
            'webhook'           => ['required', 'integer'],
            'authentication'    => ['required', 'boolean'],
            'regenerate_secret' => ['nullable', 'boolean'],
        ];
    }
}
