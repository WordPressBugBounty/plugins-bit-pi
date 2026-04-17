<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use Automatic_Upgrader_Skin;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use Plugin_Upgrader;

final class SmtpController
{
    private const PLUGIN_FILE = 'bit-smtp/bit_smtp.php';

    private const PLUGIN_URL = 'https://downloads.wordpress.org/plugin/bit-smtp.latest-stable.zip';

    public function status()
    {
        return Response::success(
            [
                'installed' => $this->isInstalled(),
                'active'    => $this->isActive(),
            ]
        );
    }

    public function install()
    {
        if ($this->isActive()) {
            return Response::success(['status' => 'already_active']);
        }

        if ($this->isInstalled()) {
            $activated = activate_plugin(self::PLUGIN_FILE);
            if (is_wp_error($activated)) {
                return Response::error($activated->get_error_message());
            }

            return Response::success(['status' => 'activated']);
        }

        include_once ABSPATH . 'wp-admin/includes/plugin.php';

        include_once ABSPATH . 'wp-admin/includes/file.php';

        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
        $result = $upgrader->install(self::PLUGIN_URL);

        if (is_wp_error($result)) {
            return Response::error($result->get_error_message());
        }

        if (!$result) {
            return Response::error(__('Plugin installation failed.', 'bit-pi'));
        }

        $activated = activate_plugin(self::PLUGIN_FILE);
        if (is_wp_error($activated)) {
            return Response::error($activated->get_error_message());
        }

        return Response::success(['status' => 'installed_and_activated']);
    }

    private function isInstalled(): bool
    {
        return file_exists(WP_PLUGIN_DIR . '/' . self::PLUGIN_FILE);
    }

    private function isActive(): bool
    {
        return is_plugin_active(self::PLUGIN_FILE);
    }
}
