<?php

namespace BitApps\Pi\src\Integrations\WpForms;

use BitApps\Pi\src\Integrations\HookRegisterInterface;

if (!defined('ABSPATH')) {
    exit;
}

class WpFormsHooks implements HookRegisterInterface
{
    public function register(): array
    {
        return [
            'processComplete' => [
                'hook'          => 'wpforms_process_complete',
                'callback'      => [WpFormsTrigger::class, 'handleSubmit'],
                'priority'      => 9999,
                'accepted_args' => 3,
            ],
        ];
    }
}
