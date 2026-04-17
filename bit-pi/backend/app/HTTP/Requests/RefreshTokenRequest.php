<?php

namespace BitApps\Pi\HTTP\Requests;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;

class RefreshTokenRequest extends Request
{
    public function rules()
    {
        return [
            'connectionId'    => ['required', 'integer'],
            'refreshTokenUrl' => ['required', 'url', 'sanitize:url'],
            'appSlug'         => ['nullable', 'string', 'sanitize:text', 'sanitize:ucfirst'],
        ];
    }
}
