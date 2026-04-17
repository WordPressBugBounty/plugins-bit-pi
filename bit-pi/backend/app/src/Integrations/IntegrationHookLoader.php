<?php

namespace BitApps\Pi\src\Integrations;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Hooks\Hooks;
use BitApps\Pi\Model\CustomApp;
use BitApps\Pi\Services\FlowService;
use BitApps\Pi\src\Flow\NodeExecutor;

final class IntegrationHookLoader
{
    /**
     * Load action hooks for the specified integration namespace.
     */
    public static function loadActionHooks()
    {
        $apps = JSON::maybeDecode(FlowService::getOrUpdateTriggerNodesFromCache());

        if (empty($apps)) {
            return;
        }

        foreach ($apps as $appSlug => $triggerSlugs) {
            if (strpos($appSlug, CustomApp::APP_SLUG_PREFIX) !== false) {
                $appSlug = ucfirst(CustomApp::APP_SLUG);
            } else {
                $appSlug = ucfirst($appSlug);
            }

            $registeredTriggers = self::getRegisteredTriggers($appSlug);

            if ($registeredTriggers === false) {
                continue;
            }

            foreach ($triggerSlugs as $triggerSlug) {
                if (!isset($registeredTriggers[$triggerSlug])) {
                    continue;
                }

                $trigger = $registeredTriggers[$triggerSlug];

                if (!isset($trigger['hook'], $trigger['callback'])) {
                    continue;
                }

                $hookList = $trigger['hook'];

                $callback = $trigger['callback'];

                $priority = $trigger['priority'] ?? 10;

                $acceptedArgs = $trigger['accepted_args'] ?? 1;

                if (!\is_callable($callback)) {
                    continue;
                }

                if (\is_array($hookList)) {
                    foreach ($hookList as $hook) {
                        Hooks::addAction($hook, $callback, $priority, $acceptedArgs);
                    }
                } else {
                    Hooks::addAction($hookList, $callback, $priority, $acceptedArgs);
                }
            }
        }
    }

    private static function getRegisteredTriggers($appSlug)
    {
        $freeHookRegisterClass = NodeExecutor::BASE_INTEGRATION_NAMESPACE . "{$appSlug}\\{$appSlug}Hooks";

        if (class_exists($freeHookRegisterClass) && method_exists($freeHookRegisterClass, 'register')) {
            return (new $freeHookRegisterClass())->register();
        }

        $proHookRegisterClass = NodeExecutor::BASE_INTEGRATION_NAMESPACE_PRO . "{$appSlug}\\{$appSlug}Hooks";

        if (class_exists($proHookRegisterClass) && method_exists($proHookRegisterClass, 'register')) {
            return (new $proHookRegisterClass())->register();
        }

        return false;
    }
}
