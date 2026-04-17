<?php

namespace BitApps\Pi\Services;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

class SystemInfo
{
    /**
     * Get comprehensive system information.
     *
     * @return array system information data
     */
    public static function get(): array
    {
        global $wp_version, $wpdb;

        if (!$wp_version) {
            $wp_version = get_bloginfo('version');
        }

        $dbServerInfo = method_exists($wpdb, 'db_server_info') ? $wpdb->db_server_info() : '';

        $mysqlType = stripos($dbServerInfo, 'mariadb') !== false ? 'MariaDB' : 'MySQL';

        $mysqlVersion = method_exists($wpdb, 'db_version') ? $wpdb->db_version() : 'Unknown';

        return [
            'wordpress' => [
                'version'          => $wp_version,
                'is_multisite'     => is_multisite(),
                'is_ssl'           => is_ssl(),
                'debug_mode'       => defined('WP_DEBUG') && WP_DEBUG,
                'debug_log'        => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
                'timezone'         => wp_timezone_string(),
                'wp_cron_disabled' => defined('DISABLE_WP_CRON') && \constant('DISABLE_WP_CRON'),
            ],
            'server' => [
                'software'        => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'Unknown',
                'php_version'     => PHP_VERSION,
                'php_os'          => PHP_OS,
                'php_sapi'        => PHP_SAPI,
                'curl_version'    => \function_exists('curl_version') ? curl_version()['version'] : 'Not available',
                'openssl_version' => defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'Not available',
            ],
            'database' => [
                'table_prefix'  => $wpdb->prefix,
                'mysql_version' => $mysqlVersion,
                'mysql_type'    => $mysqlType,
            ],
            'php_config' => [
                'memory_limit'        => \ini_get('memory_limit'),
                'max_upload_size'     => size_format(wp_max_upload_size()),
                'post_max_size'       => \ini_get('post_max_size'),
                'max_execution_time'  => \ini_get('max_execution_time'),
                'max_input_vars'      => \ini_get('max_input_vars'),
                'upload_max_filesize' => \ini_get('upload_max_filesize'),
                'allow_url_fopen'     => (bool) \ini_get('allow_url_fopen'),
            ],
            'php_extensions' => [
                'curl'     => \extension_loaded('curl'),
                'mbstring' => \extension_loaded('mbstring'),
                'zip'      => \extension_loaded('zip'),
                'fileinfo' => \extension_loaded('fileinfo'),
                'json'     => \extension_loaded('json'),
                'openssl'  => \extension_loaded('openssl'),
            ],
        ];
    }
}
