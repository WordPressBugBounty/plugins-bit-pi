<?php

namespace BitApps\Pi\src\Integrations;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


class AIIntegrationHelper
{
    public static function castPayloadTypes($data)
    {
        if (isset($data['max_tokens'])) {
            $data['max_tokens'] = (int) $data['max_tokens'];
        }
        if (isset($data['temperature'])) {
            $data['temperature'] = (float) $data['temperature'];
        }
        if (isset($data['top_p'])) {
            $data['top_p'] = (float) $data['top_p'];
        }
        if (isset($data['seed'])) {
            $data['seed'] = (int) $data['seed'];
        }
        if (isset($data['frequency_penalty'])) {
            $data['frequency_penalty'] = (float) $data['frequency_penalty'];
        }
        if (isset($data['presence_penalty'])) {
            $data['presence_penalty'] = (float) $data['presence_penalty'];
        }

        return $data;
    }
}
