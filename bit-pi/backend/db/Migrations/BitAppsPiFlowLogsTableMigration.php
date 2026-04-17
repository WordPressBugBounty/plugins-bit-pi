<?php

use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPDatabase\Blueprint;
use BitApps\Pi\Deps\BitApps\WPDatabase\Connection;
use BitApps\Pi\Deps\BitApps\WPDatabase\Schema;
use BitApps\Pi\Deps\BitApps\WPKit\Migration\Migration;
use BitApps\Pi\Model\FlowLog;

if (!defined('ABSPATH')) {
    exit;
}

final class BitAppsPiFlowLogsTableMigration extends Migration // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
{
    public function up(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->create(
            'flow_logs',
            function (Blueprint $table): void {
                $table->id();
                $table->bigint('flow_history_id', 20)->unsigned()->foreign('flow_histories', 'id')->onDelete()->cascade();
                $table->string('node_id');
                $table->enum('status', array_values(FlowLog::STATUS));
                // $table->bigint('parent_node_id', 20)->nullable(); TODO:: Add next version
                $table->mediumtext('messages')->nullable();
                $table->longtext('input')->nullable();
                $table->longtext('output')->nullable();
                $table->mediumtext('details')->nullable();
                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->drop('flow_logs');
    }
}
