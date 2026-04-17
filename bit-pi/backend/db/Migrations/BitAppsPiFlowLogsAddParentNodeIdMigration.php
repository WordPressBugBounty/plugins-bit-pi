<?php

use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Migration\Migration;

if (!defined('ABSPATH')) {
    exit;
}

final class BitAppsPiFlowLogsAddParentNodeIdMigration extends Migration // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
{
    public function up()
    {
        // TODO:: Add next version
        // try {
        //     global $wpdb;
        //     $table_name = Config::withDBPrefix('flow_logs');
        //     $sql = "ALTER TABLE {$table_name} ADD COLUMN parent_node_id VARCHAR(191) NULL AFTER node_id;";
        //     $wpdb->query($sql);
        // } catch (Exception $e) {
        //     // Handle exception if needed
        // }
    }

    public function down()
    {
        // TODO:: Add next version
    }
}
