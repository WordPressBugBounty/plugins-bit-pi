<?php

use BitApps\Pi\Deps\BitApps\WPKit\Http\Router\Route;
use BitApps\Pi\HTTP\Controllers\FlowNodeTestController;
use BitApps\Pi\HTTP\Controllers\RedirectController;

if (!defined('ABSPATH')) {
    exit;
}


Route::get('oauthCallback', [RedirectController::class, 'handleCallback']);
// Route::match(['post', 'get'],'capture/{trigger_id}',[WebhookController::class, 'captureWebhook']);
// Route::post('test', [FlowNodeTestController::class, 'testNodeExecute']);
