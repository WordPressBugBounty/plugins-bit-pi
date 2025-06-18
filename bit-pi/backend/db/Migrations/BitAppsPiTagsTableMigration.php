<?php

use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPDatabase\Blueprint;
use BitApps\Pi\Deps\BitApps\WPDatabase\Connection;
use BitApps\Pi\Deps\BitApps\WPDatabase\Schema;
use BitApps\Pi\Deps\BitApps\WPKit\Migration\Migration;

if (!defined('ABSPATH')) {
    exit;
}

final class BitAppsPiTagsTableMigration extends Migration
{
    public function up(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->create(
            'tags',
            function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->string('slug');
                $table->string('filter')->nullable();
                $table->boolean('status')->defaultValue(0);
                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::withPrefix(Connection::wpPrefix() . Config::VAR_PREFIX)->drop('tags');
    }
}
