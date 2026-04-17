<?php

namespace BitApps\Pi\src\Integrations\Webhook;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Integrations\ApiRequest\ApiRequestAction;

class WebhookAction extends ApiRequestAction
{
    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        parent::__construct($nodeInfoProvider);
    }
}
