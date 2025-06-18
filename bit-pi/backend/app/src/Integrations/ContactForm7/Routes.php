<?php

use BitApps\Pi\Deps\BitApps\WPKit\Http\Router\Route;
use BitApps\Pi\src\Integrations\ContactForm7\ContactForm7Helper;

if (!defined('ABSPATH')) {
    exit;
}


Route::group(
    function () {
        Route::post('contact-form-7/get-forms', [ContactForm7Helper::class, 'getForms']);
    }
)->middleware('nonce:admin');
