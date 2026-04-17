<?php

namespace BitApps\Pi\HTTP\Requests;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;

class WebhookUpdateTitleRequest extends Request
{
    public function rules()
    {
        return [
            'title'   => ['required', 'string', 'sanitize:text', 'max:255'],
            'webhook' => ['required', 'integer'],
        ];
    }

    public function messages()
    {
        return [
            'webhook.required' => 'Webhook ID is required',
            'webhook.integer'  => 'Webhook ID must be a valid integer',
        ];
    }
}
