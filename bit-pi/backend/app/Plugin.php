<?php

namespace BitApps\Pi;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Hooks\Hooks;
use BitApps\Pi\Deps\BitApps\WPKit\Http\RequestType;
use BitApps\Pi\Deps\BitApps\WPKit\Migration\MigrationHelper;
use BitApps\Pi\Deps\BitApps\WPKit\Utils\Capabilities;
use BitApps\Pi\Deps\BitApps\WPTelemetry\Telemetry\Telemetry;
use BitApps\Pi\Deps\BitApps\WPTelemetry\Telemetry\TelemetryConfig;
use BitApps\Pi\HTTP\Controllers\OauthCallbackController;
use BitApps\Pi\HTTP\Controllers\PluginImprovementController;
use BitApps\Pi\HTTP\Middleware\AdminCheckerMiddleware;
use BitApps\Pi\HTTP\Middleware\NonceCheckerMiddleware;
use BitApps\Pi\Providers\HookProvider;
use BitApps\Pi\Providers\InstallerProvider;
use BitApps\Pi\Providers\RewriteRuleProvider;
use BitApps\Pi\src\Flow\FlowExecutor;
use BitApps\Pi\Views\HtmlTagModifier;
use BitApps\Pi\Views\Layout;
use BitApps\Pi\Views\PluginPageActions;

final class Plugin
{
    /**
     * Main instance of the plugin.
     *
     * @since 1.0.0-alpha
     *
     * @var null|Plugin
     */
    private static $_instance;

    private $_registeredMiddleware = [];

    /**
     * Initialize the Plugin with hooks.
     */
    public function __construct()
    {
        // Connection::setPluginPrefix(Config::VAR_PREFIX);

        $this->registerInstaller();


        $requestUri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';

        if (!RequestType::is(RequestType::API) && !RequestType::is(RequestType::AJAX) && !RequestType::is(RequestType::CRON) && strpos($requestUri, 'wp-content') === false) {
            $rewriteRules = get_option('rewrite_rules');

            $oAuthCallbackRoute = '^' . Config::SLUG . '/oauth-callback/?$';

            // check if bit-pi/oauth-callback exists in rewrite rules
            if (!isset($rewriteRules[$oAuthCallbackRoute])) {
                new RewriteRuleProvider(Config::SLUG . '/oauth-callback');
            }

            Hooks::addAction('template_include', [new OauthCallbackController(), 'handleOauthCallback']);
        }

        Hooks::addAction('plugins_loaded', [$this, 'loaded']);

        Hooks::addFilter(Config::withPrefix('telemetry_additional_data'), [new PluginImprovementController(), 'filterTrackingData']);

        if (!Config::getEnv('DEV')) {
            $this->initWPTelemetry();
        }
    }

    public function registerInstaller()
    {
        $installerProvider = new InstallerProvider();
        $installerProvider->register();
    }

    /**
     * Load the plugin.
     */
    public function loaded()
    {
        Hooks::doAction(Config::withPrefix('loaded'));

        Hooks::addAction('init', [$this, 'registerProviders'], 8);

        Hooks::addFilter('plugin_action_links_' . Config::get('BASENAME'), [new PluginPageActions(), 'renderActionLinks']);

        Hooks::addAction(Config::VAR_PREFIX . 'background_process_request_cron', [new FlowExecutor(), 'checkQueueAndRestartBackgroundProcess']);

        $this->maybeMigrateDB();
    }

    public function initWPTelemetry()
    {
        TelemetryConfig::setSlug(Config::SLUG);
        TelemetryConfig::setTitle(Config::TITLE);
        TelemetryConfig::setVersion(Config::VERSION);
        TelemetryConfig::setPrefix(Config::VAR_PREFIX);

        TelemetryConfig::setServerBaseUrl('https://wp-api.bitapps.pro/public/');
        TelemetryConfig::setTermsUrl('https://bitapps.pro/terms-of-service/');
        TelemetryConfig::setPolicyUrl('https://bitapps.pro/privacy-policy/');

        Telemetry::report()->init();
        Telemetry::feedback()->init();
    }

    public function middlewares()
    {
        return [
            'nonce'   => NonceCheckerMiddleware::class,
            'isAdmin' => AdminCheckerMiddleware::class,
        ];
    }

    public function getMiddleware($name)
    {
        if (isset($this->_registeredMiddleware[$name])) {
            return $this->_registeredMiddleware[$name];
        }

        $middlewares = $this->middlewares();

        if (isset($middlewares[$name]) && class_exists($middlewares[$name]) && method_exists($middlewares[$name], 'handle')) {
            $this->_registeredMiddleware[$name] = new $middlewares[$name]();
        } else {
            return false;
        }

        return $this->_registeredMiddleware[$name];
    }

    /**
     * Instantiate the Provider class.
     */
    public function registerProviders()
    {
        if (RequestType::is('admin')) {
            new Layout();
            new HtmlTagModifier();
        }

        new HookProvider();
    }

    public static function maybeMigrateDB()
    {
        if (!Capabilities::check('manage_options')) {
            return;
        }

        if (version_compare(Config::getOption('db_version'), Config::DB_VERSION, '<')) {
            MigrationHelper::migrate(InstallerProvider::migration());
        }
    }

    /**
     * Retrieves the main instance of the plugin.
     *
     * @since 1.0.0-alpha
     *
     * @return Plugin plugin main instance
     */
    public static function instance()
    {
        return self::$_instance;
    }

    /**
     * Loads the plugin main instance and initializes it.
     *
     * @return bool True if the plugin main instance could be loaded, false otherwise
     *
     * @since 1.0.0-alpha
     */
    public static function load()
    {
        if (self::$_instance !== null) {
            return false;
        }

        self::$_instance = new self();

        return true;
    }
}
