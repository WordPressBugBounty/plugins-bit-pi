<?php

use BitApps\Pi\Deps\BitApps\WPKit\Http\Router\Route;
use BitApps\Pi\src\Integrations\BitForm\BitFormHelper;

if (!defined('ABSPATH')) {
    exit;
}

Route::group(
    function () {
        Route::post('bit-form/get-forms', [BitFormHelper::class, 'getForms']);
    }
)->middleware('nonce:admin');
