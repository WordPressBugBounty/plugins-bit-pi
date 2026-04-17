<?php

namespace BitApps\Pi\src\Integrations\ElementorForm;

use BitApps\Pi\Helpers\Node;
use BitApps\Pi\Services\FlowService;
use BitApps\Pi\src\Flow\FlowExecutor;

if (!defined('ABSPATH')) {
    exit;
}


class ElementorFormTrigger
{
    public static function handleSubmit($record)
    {
        $flows = FlowService::exists('elementorForm', 'formsNewRecord');

        if (!$flows) {
            return;
        }

        $data = self::getExecutableData($record);

        foreach ($flows as $flow) {
            $triggerNode = Node::getNodeInfoById($flow->id . '-1', $flow->nodes);

            if (!$triggerNode) {
                continue;
            }

            FlowExecutor::execute($flow, $data);
        }
    }

    private static function getExecutableData($record)
    {
        $data = [
            'id'           => $record->get_form_settings('id'),
            'form_id'      => $record->get_form_settings('form_id'),
            'form_name'    => $record->get_form_settings('form_name'),
            'form_post_id' => $record->get_form_settings('form_post_id'),
        ];

        foreach ($record->get('fields') as $field_id => $field) {
            $data['fields'][$field_id] = $field['value'];
        }

        return $data;
    }
}
