<?php

namespace BitApps\Pi\src\Integrations\BitForm;

use BitApps\Pi\src\Integrations\HookRegisterInterface;

if (!defined('ABSPATH')) {
    exit;
}

class BitFormHooks implements HookRegisterInterface
{
    public function register(): array
    {
        return [
            'submitSuccess' => [
                'hook'          => 'bitform_submit_success',
                'callback'      => [BitFormTrigger::class, 'handleSubmit'],
                'priority'      => 10,
                'accepted_args' => 4,
            ],
        ];
    }
}
