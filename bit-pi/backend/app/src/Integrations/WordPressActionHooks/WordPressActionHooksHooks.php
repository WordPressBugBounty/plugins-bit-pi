<?php

namespace BitApps\Pi\src\Integrations\WordPressActionHooks;

use BitApps\Pi\src\Integrations\HookRegisterInterface;
use BitApps\Pi\src\Integrations\WordPress\helpers\WordPressActionHelper;

if (!defined('ABSPATH')) {
    exit;
}

class WordPressActionHooksHooks implements HookRegisterInterface
{
    /**
     * Register the hooks for WordPress.
     */
    public function register(): array
    {
        return WordPressActionHelper::registerUserDefinedHooks('wordPressActionHooks', []);
    }
}
