<?php

namespace BitApps\Pi\src\Integrations\BitForm;

use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitCode\BitForm\API\BitForm_Public\BitForm_Public;

if (!defined('ABSPATH')) {
    exit;
}


class BitFormHelper
{
    public function getForms()
    {
        if (!$this->isActive()) {
            return Response::error('Bit Form is not installed or activated');
        }

        $allForms = [
            [
                'label' => 'Any form',
                'value' => 'any'
            ],
        ];

        foreach (BitForm_Public::getForms() as $form) {
            $allForms[] = [
                'label' => $form->form_name,
                'value' => $form->id,
            ];
        }

        return Response::success($allForms);
    }

    private function isActive()
    {
        return class_exists('BitCode\BitForm\Plugin');
    }
}
