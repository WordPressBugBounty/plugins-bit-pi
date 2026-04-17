<?php

use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPDatabase\Blueprint;
use BitApps\Pi\Deps\BitApps\WPDatabase\Connection;
use BitApps\Pi\Deps\BitApps\WPDatabase\Schema;
use BitApps\Pi\Deps\BitApps\WPKit\Migration\Migration;

if (!defined('ABSPATH')) {
    exit;
}

final class BitAppsPiWebhooksTableMigration extends Migration // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
{
    public function up(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->create(
            'webhooks',
            function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->bigint('flow_id', 20)->nullable()->unique()->unsigned()->foreign('flows', 'id')->onDelete()->setNull();
                $table->string('app_slug');
                $table->string('webhook_slug');
                $table->longtext('ip_restrictions')->nullable();
                $table->longtext('details')->nullable();
                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->drop('webhooks');
    }
}
