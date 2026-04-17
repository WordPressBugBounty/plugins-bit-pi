<?php

if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Router\Route;
use BitApps\PiPro\src\Integrations\FacebookLeads\FacebookLeadsTrigger;

Route::group(
    function () {
        Route::post('facebook-leads/subscribe-webhook', [FacebookLeadsTrigger::class, 'subscribeWebhook']);
    }
)->middleware('nonce:admin');
