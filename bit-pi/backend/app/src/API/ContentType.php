<?php

namespace BitApps\Pi\src\API;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


final class ContentType
{
    public const URL_ENCODED = 'application/x-www-form-urlencoded';

    public const JSON = 'application/json';

    public const XML = 'application/xml';

    public const FORM_DATA = 'multipart/form-data';

    public const PLAIN_TEXT = 'text/plain';

    public const HTML = 'text/html';
}
