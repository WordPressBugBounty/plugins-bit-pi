<?php

namespace BitApps\Pi\src\Integrations\Http;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Integrations\ApiRequest\ApiRequestAction;

class HttpAction extends ApiRequestAction
{
    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        parent::__construct($nodeInfoProvider);
    }
}
