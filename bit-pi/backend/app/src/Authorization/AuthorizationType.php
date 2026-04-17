<?php

namespace BitApps\Pi\src\Authorization;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


/**
 * Class AuthorizationType.
 *
 * Defines constants for different types of authorization.
 *
 * different authentication mechanisms.
 */
class AuthorizationType
{
    /**
     * Basic Authentication (Username & Password).
     */
    public const BASIC_AUTH = 'basic_auth';

    /**
     * API Key Authentication.
     */
    public const API_KEY = 'api_key';

    /**
     * Bearer Token Authentication (e.g., JWT or OAuth tokens).
     */
    public const BEARER_TOKEN = 'bearer_token';

    /**
     * OAuth2 Authentication.
     */
    public const OAUTH2 = 'oauth2';

    /**
     * Custom Authentication (Defined per application).
     */
    public const CUSTOM = 'custom';
}
