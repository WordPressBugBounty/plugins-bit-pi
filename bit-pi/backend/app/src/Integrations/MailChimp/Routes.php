<?php

use BitApps\Pi\Deps\BitApps\WPKit\Http\Router\Route;
use BitApps\Pi\src\Integrations\MailChimp\deprecated\MailChimpDeprecatedHelper;
use BitApps\Pi\src\Integrations\MailChimp\MailChimpHelper;

if (!defined('ABSPATH')) {
    exit;
}

Route::group(
    function () {
        Route::post('mailchimp/get-audience-fields', [MailChimpDeprecatedHelper::class, 'getAudienceFields']);
        Route::post('mailchimp/set-mailchimp-webhook', [MailChimpHelper::class, 'setMailchimpWebhook']);
    }
)->middleware('nonce:admin');
