<?php

namespace BitApps\Pi\src\Integrations;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Helpers\Node;
use BitApps\Pi\src\Flow\FlowExecutor;
use BitApps\Pi\src\Flow\NodeInfoProvider;

class IntegrationHelper
{
    public static function handleFlowForForm($flows, $data, $value = null, $keyName = 'form-id', $valueType = 'int')
    {
        foreach ($flows as $flow) {
            $triggerNode = Node::getNodeInfoById($flow->id . '-1', $flow->nodes);

            if (!$triggerNode) {
                continue;
            }

            if ($value) {
                $nodeHelper = new NodeInfoProvider($triggerNode);

                $configuredFormValue = $nodeHelper->getFieldMapConfigs($keyName . '.value');

                if ($configuredFormValue !== 'any') {
                    if ($valueType === 'int') {
                        $value = (int) $value;
                        $configuredFormValue = (int) $configuredFormValue;
                    }

                    if ($configuredFormValue !== $value) {
                        continue;
                    }
                }
            }

            FlowExecutor::execute($flow, $data);
        }
    }
}
