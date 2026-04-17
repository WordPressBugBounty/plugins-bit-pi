<?php

namespace BitApps\Pi\src\Integrations\WpForms;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\Services\FlowService;
use BitApps\Pi\src\Integrations\IntegrationHelper;

final class WpFormsTrigger
{
    public function getAllForms()
    {
        if (!\function_exists('WPForms')) {
            return Response::error(__('WPForms is not installed or activated.', 'bit-pi'));
        }

        $forms = WPForms()->form->get();

        $allForms[] = [
            'label' => 'Any form',
            'value' => 'any'
        ];

        if (!$forms) {
            return Response::success('No forms found.');
        }

        foreach ($forms as $form) {
            $allForms[] = (object) [
                'value' => $form->ID,
                'label' => $form->post_title,
            ];
        }

        return Response::success($allForms);
    }

    public static function handleSubmit($fields, $entry, $form_data)
    {
        $formId = $form_data['id'];

        $data = [];

        $flows = FlowService::exists('wpForms', 'processComplete');

        if (!$flows || !$formId) {
            return;
        }

        if (isset($entry['post_id'])) {
            $data['post_id'] = $entry['post_id'];
        }

        foreach ($fields as $field) {
            if ($field['type'] === 'name') {
                $data[$field['id']] = $field['value'];
                $data[$field['id'] . ':first'] = $field['first'];
                $data[$field['id'] . ':last'] = $field['last'];
                $data[$field['id'] . ':middle'] = $field['middle'];
            } elseif ($field['type'] === 'file-upload') {
                $data[$field['id']] = self::setFileUploadFieldsValue($field['value_raw']);
            } else {
                $data[$field['id']] = $field['value'];
            }
        }

        IntegrationHelper::handleFlowForForm($flows, $data, $formId);
    }

    private static function setFileUploadFieldsValue($files)
    {
        $allFiles = [];

        foreach ($files as $file) {
            $allFiles[] = $file['value'];
        }

        return $allFiles;
    }
}
