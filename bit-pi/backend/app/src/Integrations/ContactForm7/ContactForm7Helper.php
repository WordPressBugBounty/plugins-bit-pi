<?php

namespace BitApps\Pi\src\Integrations\ContactForm7;

use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use WPCF7_ContactForm;

if (!defined('ABSPATH')) {
    exit;
}


class ContactForm7Helper
{
    public function getForms()
    {
        if (!class_exists('WPCF7_ContactForm')) {
            return Response::error('Contact Form 7 is not installed or activated');
        }

        $formOptions = [
            [
                'label' => 'Any form',
                'value' => 'any'
            ],
        ];

        $forms = WPCF7_ContactForm::find();

        foreach ($forms as $form) {
            $formOptions[] = [
                'label' => $form->title(),
                'value' => $form->id()
            ];
        }

        return Response::success($formOptions);
    }
}
