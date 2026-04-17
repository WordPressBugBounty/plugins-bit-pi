<?php

namespace BitApps\Pi\src\Integrations\ContactForm7;

use BitApps\Pi\src\Integrations\HookRegisterInterface;

if (!defined('ABSPATH')) {
    exit;
}

class ContactForm7Hooks implements HookRegisterInterface
{
    public function register(): array
    {
        return [
            'formSubmitted' => [
                'hook'          => 'wpcf7_before_send_mail',
                'callback'      => [ContactForm7Trigger::class, 'handleSubmit'],
                'priority'      => 10,
                'accepted_args' => 0,
            ],
        ];
    }
}
