<?php

namespace BitApps\Pi\HTTP\Requests;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;

class WebhookUpdateRequest extends Request
{
    public function rules()
    {
        return [
            'flow_id' => ['required', 'integer'],
        ];
    }
}
