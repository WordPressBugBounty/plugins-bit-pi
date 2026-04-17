<?php

use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Migration\Migration;

if (!defined('ABSPATH')) {
    exit;
}
final class BitAppsPiFlowNodesAddMachineLabelMigration extends Migration // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
{
    public function up(): void
    {
        global $wpdb;
        $table = Config::withDBPrefix('flow_nodes');
        $col = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'machine_label'"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if (empty($col)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN machine_label VARCHAR(191) NULL AFTER machine_slug"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
    }

    public function down(): void
    {
        global $wpdb;
        $table = Config::withDBPrefix('flow_nodes');
        $col = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'machine_label'"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if (!empty($col)) {
            $wpdb->query("ALTER TABLE {$table} DROP COLUMN machine_label"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
    }
}
