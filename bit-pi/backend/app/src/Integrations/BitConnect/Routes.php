<?php

if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Router\Route;
use BitApps\Pi\src\Integrations\BitConnect\helper\BitConnectHelper;

Route::group(
    function () {
        Route::get('bit-connect/posts', [BitConnectHelper::class, 'getPosts']);
        Route::get('bit-connect/members', [BitConnectHelper::class, 'getMembers']);
        Route::get('bit-connect/tags', [BitConnectHelper::class, 'getTags']);
        Route::get('bit-connect/topic-types', [BitConnectHelper::class, 'getTopicTypes']);
        Route::get('bit-connect/statuses', [BitConnectHelper::class, 'getStatuses']);
        Route::get('bit-connect/stages', [BitConnectHelper::class, 'getStages']);
        Route::get('bit-connect/badges', [BitConnectHelper::class, 'getBadges']);
        Route::get('bit-connect/groups', [BitConnectHelper::class, 'getGroups']);
    }
)->middleware('nonce', 'isAdmin');
