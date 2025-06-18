<?php

use BitApps\Pi\Dotenv;
use BitApps\Pi\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

Dotenv::load(plugin_dir_path(__DIR__) . '.env');

Plugin::load();
