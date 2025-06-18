<?php

namespace BitApps\Pi\Providers;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Hooks\Hooks;
use BitApps\Pi\Deps\BitApps\WPKit\Http\RequestType;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Router\Router;
use BitApps\Pi\Plugin;
use BitApps\Pi\Services\FlowHistoryService;
use BitApps\Pi\src\Integrations\IntegrationHookLoader;
use FilesystemIterator;

class HookProvider
{
    private $_pluginBackend;

    public function __construct()
    {
        $this->_pluginBackend = Config::get('BASEDIR') . DIRECTORY_SEPARATOR;
        $this->loadIntegrationsAjaxHook();
        IntegrationHookLoader::loadActionHooks();
        $this->loadAppAjaxHooks();
        Hooks::addAction(Config::VAR_PREFIX . 'flow_history_cleanup', [FlowHistoryService::class, 'flowHistoryCleanup']);
        Hooks::addAction('rest_api_init', [$this, 'loadAppApiHooks']);
        Hooks::addFilter('safe_style_css', [$this, 'allowStyleProperties']);

        if (!wp_next_scheduled(Config::VAR_PREFIX . 'flow_history_cleanup')) {
            wp_schedule_event(time(), 'daily', Config::VAR_PREFIX . 'flow_history_cleanup');
        }

        if (Config::getEnv('CLI_ACTIVE')) {
            include_once __DIR__ . '/../../../cli/RegisterCommands.php';
        }
    }

    public function allowStyleProperties($styles)
    {
        $styles[] = 'display';

        return $styles;
    }

    public function loadAppApiHooks()
    {
        if (
            is_readable($this->_pluginBackend . 'hooks' . DIRECTORY_SEPARATOR . 'api.php')
            && RequestType::is(RequestType::API)
        ) {
            $router = new Router(RequestType::API, Config::SLUG, 'v1');

            include $this->_pluginBackend . 'hooks' . DIRECTORY_SEPARATOR . 'api.php';
            $router->register();
        }
    }

    /**
     * Helps to register App hooks.
     */
    protected function loadAppAjaxHooks()
    {
        if (
            RequestType::is(RequestType::AJAX)
            && is_readable($this->_pluginBackend . 'hooks' . DIRECTORY_SEPARATOR . 'ajax.php')
        ) {
            $router = new Router(RequestType::AJAX, Config::VAR_PREFIX, '');
            $router->setMiddlewares(Plugin::instance()->middlewares());

            include $this->_pluginBackend . 'hooks' . DIRECTORY_SEPARATOR . 'ajax.php';
            $router->register();
        }
    }

    /**
     * Helps to register Triggers & Ajax Hook.
     */
    protected function loadIntegrationsAjaxHook()
    {
        $taskDir = $this->_pluginBackend . 'app' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Integrations';
        $dirs = new FilesystemIterator($taskDir);

        foreach ($dirs as $dirInfo) {
            if ($dirInfo->isDir()) {
                $taskName = basename($dirInfo);
                $taskPath = $taskDir . DIRECTORY_SEPARATOR . $taskName . DIRECTORY_SEPARATOR;

                if (is_readable($taskPath . 'Routes.php') && RequestType::is('ajax') && RequestType::is('admin')) {
                    $router = new Router(RequestType::AJAX, Config::VAR_PREFIX, '');
                    $router->setMiddlewares(Plugin::instance()->middlewares());

                    include $taskPath . 'Routes.php';
                    $router->register();
                }
            }
        }
    }
}
