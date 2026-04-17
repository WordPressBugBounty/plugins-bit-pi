<?php

use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPDatabase\Blueprint;
use BitApps\Pi\Deps\BitApps\WPDatabase\Connection;
use BitApps\Pi\Deps\BitApps\WPDatabase\Schema;
use BitApps\Pi\Deps\BitApps\WPKit\Migration\Migration;

if (!defined('ABSPATH')) {
    exit;
}

final class BitAppsPiCustomMachinesTableMigration extends Migration // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
{
    public function up(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->create(
            'custom_machines',
            function (Blueprint $table): void {
                $table->id();
                $table->bigint('custom_app_id', 20)->unsigned()->foreign('custom_apps', 'id')->onDelete()->cascade();
                $table->bigint('connection_id', 20)->nullable()->unsigned()->foreign('connections', 'id')->onDelete()->setNull();
                $table->string('name');
                $table->string('slug');
                $table->enum('app_type', ['trigger', 'action']);
                $table->enum('trigger_type', ['wp_hook', 'webhook', 'schedule'])->nullable();
                $table->longtext('config');
                $table->tinyint('status')->defaultValue(1);
                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->drop('custom_machines');
    }
}
