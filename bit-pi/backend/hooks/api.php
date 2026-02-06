<?php

use BitApps\Pi\Deps\BitApps\WPKit\Http\Router\Route;
use BitApps\Pi\HTTP\Controllers\RedirectController;
use BitApps\Pi\HTTP\Controllers\WebhookDispatchController;

if (!defined('ABSPATH')) {
    exit;
}


Route::get('oauthCallback', [RedirectController::class, 'handleCallback']);
Route::match(['post', 'get'], 'webhook/callback/{trigger_id}', [WebhookDispatchController::class, 'handleWebhook']);
