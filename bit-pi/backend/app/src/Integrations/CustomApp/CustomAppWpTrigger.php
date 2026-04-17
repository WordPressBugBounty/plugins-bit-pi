<?php

namespace BitApps\Pi\src\Integrations\CustomApp;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Helpers\Node;
use BitApps\Pi\Model\CustomApp;
use BitApps\Pi\Services\FlowService;
use BitApps\Pi\src\Flow\FlowExecutor;

class CustomAppWpTrigger
{
    public static function captureAddActionHookData(...$arguments)
    {
        $flows = FlowService::exists(CustomApp::APP_SLUG_PREFIX . '%', null, [], [], 'LIKE');

        if (!$flows) {
            return;
        }

        foreach ($flows as $flow) {
            $triggerNode = Node::getNodeInfoById($flow->id . '-1', $flow->nodes);

            if (!$triggerNode) {
                continue;
            }

            if (!isset($triggerNode->field_mapping->configs->{'wp-action-hook'})) {
                continue;
            }

            $hookName = $triggerNode->field_mapping->configs->{'wp-action-hook'}->value;

            if ($hookName !== current_action()) {
                continue;
            }

            foreach ($arguments as $key => $argument) {
                if (\is_object($argument) && class_exists(\get_class($argument))) {
                    $arguments[$key] = json_decode(wp_json_encode($argument), true);
                }
            }

            FlowExecutor::execute($flow, $arguments);
        }
    }
}
