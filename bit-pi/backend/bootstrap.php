<?php

use BitApps\Pi\Dotenv;
use BitApps\Pi\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    add_action(
        'admin_notices',
        static function () {
            $message = sprintf(
                // translators: %s: composer command
                __('Vendor dependencies are missing. Please run %s in the plugin directory.', 'bit-pi'),
                '<code>composer install</code>'
            );

            printf(
                '<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
                esc_html__('Bit Flows:', 'bit-pi'),
                wp_kses($message, ['code' => []])
            );
        }
    );

    return;
}

require_once __DIR__ . '/../vendor/autoload.php';

Dotenv::load(plugin_dir_path(__DIR__) . '.env');

Plugin::load();
