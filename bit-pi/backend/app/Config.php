<?php

namespace BitApps\Pi;

use BitApps\Pi\src\Menu;
use BitApps\Pi\Views\Body;
use BitApps\Pi\Views\PluginPageActions;
use BitApps\PiPro\Config as ProConfig;

if (!\defined('ABSPATH')) {
    exit;
}

/**
 * Provides App configurations.
 */
class Config
{
    public const SLUG = 'bit-pi';

    public const PRO_PLUGIN_SLUG = 'bit-pi-pro';

    public const TITLE = 'Bit Flows';

    public const VAR_PREFIX = 'bit_pi_';

    public const VERSION = '1.6.0';

    public const DB_VERSION = '0.1.0';

    public const REQUIRED_PHP_VERSION = '7.4';

    public const REQUIRED_WP_VERSION = '5.0';

    public const API_VERSION = '1.0';

    public const APP_BASE = '../../' . self::SLUG . '.php';

    public const CLASS_PREFIX = 'BitAppsPi';

    public const ASSETS_FOLDER = 'assets';

    public const PRO_PLUGIN_NAMESPACE = 'BitApps\PiPro\\';

    /**
     * Provides configuration for plugin.
     *
     * @param string $type    Type of conf
     * @param string $default Default value
     *
     * @return null|array|string
     */
    public static function get($type, $default = null)
    {
        switch ($type) {
            case 'MAIN_FILE':
                return realpath(__DIR__ . DIRECTORY_SEPARATOR . self::APP_BASE);

            case 'BASENAME':
                return plugin_basename(trim(self::get('MAIN_FILE')));

            case 'ROOT_DIR':
            case 'ROOT_DIR':
                return plugin_dir_path(self::get('MAIN_FILE'));

            case 'BASEDIR':
                return self::get('ROOT_DIR') . 'backend';

            case 'UPLOAD_BASE_URL':
                return wp_upload_dir()['baseurl'];

            case 'UPLOAD_BASE_DIR':
                return wp_upload_dir()['basedir'];

            case 'SITE_URL':
                return site_url();

            case 'ADMIN_URL':
                return str_replace(self::get('SITE_URL'), '', get_admin_url());

            case 'API_URL':
                global $wp_rewrite;

                return [
                    'base'      => get_rest_url(null, self::SLUG . '/v1'),
                    'separator' => $wp_rewrite->permalink_structure ? '?' : '&',
                ];

            case 'ROOT_URI':
                return set_url_scheme(plugins_url('', self::get('MAIN_FILE')), wp_parse_url(home_url())['scheme']);

            case 'ASSET_URI':
                if (self::isProActivated()) {
                    return ProConfig::get('ASSET_URI');
                }

                return self::get('ROOT_URI') . '/' . self::ASSETS_FOLDER;

            case 'PLUGIN_PAGE_LINKS':
                return (new PluginPageActions())->getActionLinks();

            case 'SIDE_BAR_MENU':
                return Menu::getSideBarMenu(new Body());

            case 'BUILD_CODE_NAME':
                if (self::getEnv('DEV')) {
                    return '';
                }

                if (self::isProActivated()) {
                    return file_get_contents(ProConfig::get('ROOT_DIR') . self::ASSETS_FOLDER . '/build-code-name.txt');
                }

                return file_get_contents(self::get('ROOT_DIR') . self::ASSETS_FOLDER . '/build-code-name.txt');

            case 'WP_DB_PREFIX':
                global $wpdb;

                return $wpdb->prefix;

            case 'REDIRECT_URI':
                $isPlainPermalink = get_option('permalink_structure') === '';

                if ($isPlainPermalink) {
                    return self::get('SITE_URL') . '/?pagename=' . self::SLUG . '-oauth-callback';
                }

                return self::get('SITE_URL') . '/' . Config::SLUG . '/oauth-callback/';

            default:
                return $default;
        }
    }

    /**
     * Prefixed variable name with prefix.
     *
     * @param string $option Variable name
     *
     * @return array
     */
    public static function withPrefix($option)
    {
        return self::VAR_PREFIX . $option;
    }

    /**
     * Prefixed table name with db prefix and var prefix.
     *
     * @param mixed $table
     *
     * @return string
     */
    public static function withDBPrefix($table)
    {
        return self::get('WP_DB_PREFIX') . self::withPrefix($table);
    }

    /**
     * Retrieves options from option table.
     *
     * @param string $option  Option name
     * @param bool   $default default value
     * @param bool   $wp      Whether option is default wp option
     *
     * @return mixed
     */
    public static function getOption($option, $default = false, $wp = false)
    {
        if ($wp) {
            return get_option($option, $default);
        }

        return get_option(self::withPrefix($option), $default);
    }

    /**
     * Saves option to option table.
     *
     * @param string $option   Option name
     * @param bool   $autoload Whether option will autoload
     * @param mixed  $value
     *
     * @return bool
     */
    public static function addOption($option, $value, $autoload = false)
    {
        return add_option(self::withPrefix($option), $value, '', $autoload ? 'yes' : 'no');
    }

    /**
     * Save or update option to option table.
     *
     * @param string $option   Option name
     * @param mixed  $value    Option value
     * @param bool   $autoload Whether option will autoload
     *
     * @return bool
     */
    public static function updateOption($option, $value, $autoload = null)
    {
        return update_option(self::withPrefix($option), $value, \is_null($autoload) ? null : 'yes');
    }

    public static function deleteOption($option)
    {
        return delete_option(self::withPrefix($option));
    }

    public static function getEnv($keyName)
    {
        return isset($_ENV[Config::VAR_PREFIX . $keyName]) ? sanitize_text_field($_ENV[Config::VAR_PREFIX . $keyName]) : false;
    }

    /**
     * Check if pro plugin exist and active.
     *
     * @return bool
     */
    public static function isProActivated()
    {
        if (class_exists(ProConfig::class)) {
            return ProConfig::isPro();
        }

        return false;
    }
}
