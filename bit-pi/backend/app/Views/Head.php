<?php

namespace BitApps\Pi\Views;

use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Helpers\DateTimeHelper;

if (!defined('ABSPATH')) {
    exit;
}


class Head
{
    public const FONT_URL = 'https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap';

    /**
     * Load the asset libraries.
     *
     * @param string $currentScreen $top_level_page variable for current page
     */
    public function addHeadScripts($currentScreen)
    {
        if (strpos($currentScreen, Config::SLUG) === false) {
            return;
        }

        $version = Config::VERSION;
        $slug = Config::SLUG;
        $codeName = Config::get('BUILD_CODE_NAME');

        wp_enqueue_style($slug . '-googleapis-PRECONNECT', 'https://fonts.googleapis.com', [], $version);
        wp_enqueue_style($slug . '-gstatic-PRECONNECT-CROSSORIGIN', 'https://fonts.gstatic.com', [], $version);
        wp_enqueue_style($slug . '-font', self::FONT_URL, [], $version);

        if (Config::getEnv('DEV')) {
            // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResourceParameters.NotInFooter -- Dev server scripts; version and footer loading not applicable.
            wp_enqueue_script($slug . '-vite-client-helper-MODULE', Config::getEnv('DEV_URL') . '/src/config/devHotModule.js', [], null);
            // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResourceParameters.NotInFooter
            wp_enqueue_script($slug . '-vite-client-MODULE', Config::getEnv('DEV_URL') . '/@vite/client', [], null);
            // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResourceParameters.NotInFooter
            wp_enqueue_script($slug . '-index-MODULE', Config::getEnv('DEV_URL') . '/src/main.tsx', [], null);
        } else {
            // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NoExplicitVersion, WordPress.WP.EnqueuedResourceParameters.NotInFooter -- Version is embedded in the filename via build code name.
            wp_enqueue_script($slug . '-index-MODULE', Config::get('ASSET_URI') . "/main-{$codeName}.js", [], ''); // WARNING: Do not add version in production, it may cause unexpected behavior.
            wp_enqueue_style($slug . '-styles', Config::get('ASSET_URI') . "/main-{$slug}-ba-assets-{$codeName}.css", null, $version, 'screen');
        }

        wp_localize_script(Config::SLUG . '-index-MODULE', Config::VAR_PREFIX, self::createConfigVariable());

        if (!wp_script_is('media-upload')) {
            wp_enqueue_media();
        }
    }

    /**
     * Create config variable for js.
     *
     * @return array
     */
    public static function createConfigVariable()
    {
        $frontendVars = apply_filters(
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Prefixed via Config::withPrefix() (bit_pi_).
            Config::withPrefix('localized_script'),
            [
                'nonce'          => wp_create_nonce(Config::withPrefix('nonce')),
                'restNonce'      => wp_create_nonce('wp_rest'),
                'rootURL'        => Config::get('ROOT_URI'),
                'siteURL'        => Config::get('SITE_URL'),
                'siteBaseURL'    => is_multisite() ? network_site_url() : site_url(),
                'assetsURL'      => Config::get('ASSET_URI'),
                'pluginAdminURL' => get_admin_url(null, 'admin.php?page=' . Config::SLUG . '#'),
                'redirectUri'    => Config::get('REDIRECT_URI'),
                'ajaxURL'        => admin_url('admin-ajax.php'),
                'apiURL'         => Config::get('API_URL'),
                'wpApiURL'       => Config::get('WP_API_URL'),
                'routePrefix'    => Config::VAR_PREFIX,
                'settings'       => Config::getOption('settings'),
                'dateFormat'     => Config::getOption('date_format', false, true),
                'timeFormat'     => Config::getOption('time_format', false, true),
                'timeZone'       => DateTimeHelper::wp_timezone_string(),
                'pluginSlug'     => Config::SLUG,
                'uploadBaseUrl'  => Config::get('UPLOAD_BASE_URL'),
                'version'        => Config::VERSION,
                'lang'           => get_locale(),
            ]
        );

        if (get_locale() !== 'en_US' && file_exists(Config::get('ROOT_DIR') . '/languages/frontend-extracted-strings.php')) {
            $frontendVars['translations'] = include Config::get('ROOT_DIR') . '/languages/frontend-extracted-strings.php';
        }

        return $frontendVars;
    }
}
