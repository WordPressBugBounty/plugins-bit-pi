<?php

use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPDatabase\Blueprint;
use BitApps\Pi\Deps\BitApps\WPDatabase\Connection;
use BitApps\Pi\Deps\BitApps\WPDatabase\Schema;
use BitApps\Pi\Deps\BitApps\WPKit\Migration\Migration;
use BitApps\Pi\Model\FlowHistory;

if (!defined('ABSPATH')) {
    exit;
}

final class BitAppsPiFlowHistoryTableMigration extends Migration // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
{
    public function up(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->create(
            'flow_histories',
            function (Blueprint $table): void {
                $table->id();
                $table->bigint('flow_id', 20)->unsigned()->foreign('flows', 'id')->onDelete()->cascade();
                $table->bigint('parent_history_id', 20)->nullable();
                $table->enum('status', array_values(FlowHistory::STATUS))->defaultValue(FlowHistory::STATUS['PROCESSING']);
                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->drop('flow_histories');
    }
}
