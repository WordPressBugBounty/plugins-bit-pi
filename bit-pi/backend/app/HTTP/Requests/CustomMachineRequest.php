<?php

namespace BitApps\Pi\HTTP\Requests;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;

class CustomMachineRequest extends Request
{
    public function rules()
    {
        // here some issue is there, need to fix it
        // implement requiredif rules like laravel validation requiredif

        return [
            'custom_app_id'              => ['required', 'integer'],
            'connection_id'              => ['nullable', 'integer'],
            'name'                       => ['required', 'string', 'sanitize:text'],
            'app_type'                   => ['required', 'string', 'sanitize:text'],
            'trigger_type'               => ['nullable', 'string', 'sanitize:text'],
            'config.method'              => ['nullable', 'string', 'sanitize:text'],
            'config.url'                 => ['nullable', 'url'],
            'config.headers'             => ['nullable', 'array'],
            'config.body'                => ['nullable', 'array'],
            'config.query_params'        => ['nullable', 'array'],
            'config.content_type'        => ['nullable', 'string', 'sanitize:text'],
            'config.description'         => ['nullable', 'string', 'sanitize:wp_kses_post'],
            'config.hook_name'           => ['nullable', 'string', 'sanitize:text'],
            'config.is_body_json_enable' => ['nullable', 'boolean'],
            'config.is_auth_enabled'     => ['nullable', 'boolean'],
            'config.body_json'           => ['nullable', 'string', 'sanitize:text'],
        ];
    }
}
