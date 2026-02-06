<?php

if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Router\Route;
use BitApps\Pi\src\Integrations\WordPress\WordPressHelper;

Route::group(
    function () {
        Route::post('wordpress-core/get-user-roles', [WordPressHelper::class, 'getUserRoles']);
        Route::post('wordpress-core/get-users', [WordPressHelper::class, 'getUsers']);
        Route::post('wordpress-core/get-generated-password', [WordPressHelper::class, 'getGenerateUserPassword']);
        Route::post('wordpress-core/get-post-types', [WordPressHelper::class, 'getPostTypes']);
        Route::post('wordpress-core/get-posts', [WordPressHelper::class, 'getPosts']);
        Route::post('wordpress-core/get-post-tags', [WordPressHelper::class, 'getPostTags']);
        Route::post('wordpress-core/get-taxonomies', [WordPressHelper::class, 'getTaxonomies']);
        Route::post('wordpress-core/get-post-category', [WordPressHelper::class, 'getPostCategory']);
        Route::post('wordpress-core/get-term-by-taxonomy', [WordPressHelper::class, 'getTermsByTaxonomy']);
    }
)->middleware('nonce:admin');
