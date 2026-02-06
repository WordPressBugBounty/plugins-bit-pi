<?php

use BitApps\Pi\Deps\BitApps\WPKit\Http\Router\Route;
use BitApps\Pi\src\Integrations\WpForms\WpFormsTrigger;

if (!defined('ABSPATH')) {
    exit;
}

Route::group(
    function () {
        Route::get('wpforms/get-forms', [WpFormsTrigger::class, 'getAllForms']);
    }
)->middleware('nonce:admin');
