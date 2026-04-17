<?php

namespace BitApps\Pi\src\Integrations\BitForm;

use BitApps\Pi\Helpers\Node;
use BitApps\Pi\Services\FlowService;
use BitApps\Pi\src\Flow\FlowExecutor;
use BitApps\Pi\src\Flow\NodeInfoProvider;

if (!defined('ABSPATH')) {
    exit;
}


class BitFormTrigger
{
    public static function handleSubmit($formId, $entryId, $formData, $files)
    {
        $flows = FlowService::exists('bitForm', 'submitSuccess');

        if (!$flows) {
            return;
        }

        foreach ($flows as $flow) {
            $triggerNode = Node::getNodeInfoById($flow->id . '-1', $flow->nodes);

            if (!$triggerNode) {
                continue;
            }

            // matching submitted form
            $nodeHelper = new NodeInfoProvider($triggerNode);
            $configFormId = $nodeHelper->getFieldMapConfigs('form-id.value');

            if ($configFormId !== 'any' && $configFormId !== $formId) {
                continue;
            }

            $data = [
                'form_id'   => $formId,
                'entry_id'  => $entryId,
                'form_data' => $formData,
                'files'     => $files
            ];

            FlowExecutor::execute($flow, $data);
        }
    }
}
