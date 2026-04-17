<?php

namespace BitApps\Pi\src\Integrations\ElementorForm;

use BitApps\Pi\src\Integrations\HookRegisterInterface;

if (!defined('ABSPATH')) {
    exit;
}

class ElementorFormHooks implements HookRegisterInterface
{
    public function register(): array
    {
        return [
            'formsNewRecord' => [
                'hook'          => 'elementor_pro/forms/new_record',
                'callback'      => [ElementorFormTrigger::class, 'handleSubmit'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
        ];
    }
}
