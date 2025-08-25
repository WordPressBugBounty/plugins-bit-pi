<?php

use BitApps\Pi\Deps\BitApps\WPKit\Http\Router\Route;
use BitApps\Pi\src\Integrations\MailChimp\deprecated\MailChimpDeprecatedHelper;

if (!defined('ABSPATH')) {
    exit;
}

Route::group(
    function () {
        Route::post('mailchimp/get-audience-fields', [MailChimpDeprecatedHelper::class, 'getAudienceFields']);
    }
)->middleware('nonce:admin');
