<?php

use BitApps\Pi\Deps\BitApps\WPKit\Http\Router\Route;
use BitApps\Pi\src\Integrations\MailChimp\MailChimpHelper;

if (!defined('ABSPATH')) {
    exit;
}

Route::group(
    function () {
        Route::post('mailchimp/get-audience-fields', [MailChimpHelper::class, 'getAudienceFields']);
    }
)->middleware('nonce:admin');
