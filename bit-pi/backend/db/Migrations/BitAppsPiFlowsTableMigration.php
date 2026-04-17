<?php

use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPDatabase\Blueprint;
use BitApps\Pi\Deps\BitApps\WPDatabase\Connection;
use BitApps\Pi\Deps\BitApps\WPDatabase\Schema;
use BitApps\Pi\Deps\BitApps\WPKit\Migration\Migration;

if (!defined('ABSPATH')) {
    exit;
}

final class BitAppsPiFlowsTableMigration extends Migration // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
{
    public function up(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->create(
            'flows',
            function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->int('run_count')->defaultValue(0);
                $table->boolean('is_active')->defaultValue(1);
                $table->longtext('map');
                $table->longtext('data');
                $table->longtext('settings')->nullable();
                $table->enum('trigger_type', ['wp_hook', 'webhook', 'schedule'])->nullable();
                $table->tinyint('listener_type')->defaultValue(0);
                $table->tinyint('is_hook_capture')->defaultValue(0);
                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->drop('flows');
    }
}
