<?php

namespace BitApps\Pi\src\Integrations\WordPress;

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Integrations\HookRegisterInterface;
use BitApps\Pi\src\Integrations\WordPress\helpers\WordPressActionHelper;
use BitApps\Pi\src\Integrations\WpActionHookListener\WpActionHookListener;

if (!defined('ABSPATH')) {
    exit;
}

class WordPressHooks implements HookRegisterInterface
{
    /**
     * Register the hooks for WordPress.
     */
    public function register(): array
    {
        $triggers = [
            'addUserRole' => [
                'hook'          => 'add_user_role',
                'callback'      => [new WpActionHookListener(WordPressTasks::getAppSlug(), 'addUserRole', ['user_id', 'role']), 'captureHookData'],
                'accepted_args' => 2
            ],
            'postStatusUpdated' => [
                'hook'          => 'transition_post_status',
                'callback'      => [WordPressTrigger::class, 'postStatusUpdated'],
                'accepted_args' => 3
            ],
            'postTrashed' => [
                'hook'          => 'wp_trash_post',
                'callback'      => [new WpActionHookListener(WordPressTasks::getAppSlug(), 'postTrashed', ['post_id', 'post_status']), 'captureHookData'],
                'accepted_args' => 2
            ],
            'postDeleted' => [
                'hook'          => 'deleted_post',
                'callback'      => [new WpActionHookListener(WordPressTasks::getAppSlug(), 'postDeleted', ['post_id', 'post']), 'captureHookData'],
                'accepted_args' => 2
            ]
        ];


        $triggers = $this->registerCustomHooks($triggers);

        return WordPressActionHelper::registerUserDefinedHooks(WordPressTasks::getAppSlug(), $triggers);
    }

    /**
     * Register custom hook triggers.
     *
     * @param array $triggers
     */
    private function registerCustomHooks($triggers = [])
    {
        $customHooks = WordPressTasks::getHookList();

        foreach ($customHooks as $hookName => $customHook) {
            $numberOfArguments = $customHook['args'] ?? null;

            $priority = $customHook['priority'] ?? null;

            $machineSlug = Utility::convertToMachineSlug($hookName);

            $triggers[$machineSlug] = [
                'hook'     => $hookName,
                'callback' => [new WpActionHookListener(WordPressTasks::getAppSlug(), $machineSlug), 'captureHookData'],
            ];

            if ($priority !== null) {
                $triggers[$machineSlug]['priority'] = $priority;
            }

            if ($numberOfArguments !== null) {
                $triggers[$machineSlug]['accepted_args'] = $numberOfArguments;
            }
        }

        return $triggers;
    }
}
