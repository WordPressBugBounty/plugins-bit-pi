<?php

namespace BitApps\Pi\src\Integrations\CustomApp;

use BitApps\Pi\Model\CustomMachine;
use BitApps\Pi\src\Integrations\HookRegisterInterface;

if (!defined('ABSPATH')) {
    exit;
}

class CustomAppHooks implements HookRegisterInterface
{
    public function register(): array
    {
        $triggers = [];

        $wpTriggers = CustomMachine::select(['config', 'slug'])->where('trigger_type', 'wp_hook')->where('status', 1)->get();

        foreach ($wpTriggers as $trigger) {
            if (!isset($trigger['config']['hook_name'])) {
                continue;
            }

            $slug = $trigger['slug'];

            $hookName = $trigger['config']['hook_name'];

            $triggers[$slug] = [
                'hook'          => $hookName,
                'callback'      => [CustomAppWpTrigger::class, 'captureAddActionHookData'],
                'priority'      => 10,
                'accepted_args' => PHP_INT_MAX,
            ];
        }

        return $triggers;
    }
}
