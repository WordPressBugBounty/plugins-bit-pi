<?php

use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPDatabase\Blueprint;
use BitApps\Pi\Deps\BitApps\WPDatabase\Connection;
use BitApps\Pi\Deps\BitApps\WPDatabase\Schema;
use BitApps\Pi\Deps\BitApps\WPKit\Migration\Migration;

if (!defined('ABSPATH')) {
    exit;
}

final class BitAppsPiAppConnectionsTableMigration extends Migration // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
{
    public function up(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->create(
            'connections',
            function (Blueprint $table): void {
                $table->id();
                $table->string('app_slug');
                $table->string('auth_type');
                $table->string('connection_name');
                $table->string('account_name')->nullable();
                $table->string('encrypt_keys')->nullable();
                $table->longtext('auth_details');
                $table->tinyint('status')->defaultValue(1);
                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->drop('connections');
    }
}
