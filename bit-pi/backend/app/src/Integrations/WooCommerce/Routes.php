<?php

if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Router\Route;
use BitApps\Pi\src\Integrations\WooCommerce\helper\WooCommerceHelper;

Route::group(
    function () {
        Route::post('woocommerce-core/get-product-category', [WooCommerceHelper::class, 'getProductCategory']);
        Route::post('woocommerce-core/get-products', [WooCommerceHelper::class, 'getProducts']);
        Route::post('woocommerce-core/get-shipping-class', [WooCommerceHelper::class, 'getShippingClass']);
        Route::post('woocommerce-core/get-product-tags', [WooCommerceHelper::class, 'getProductTags']);
        Route::post('woocommerce-core/get-product-brands', [WooCommerceHelper::class, 'getProductBrands']);
        Route::post('woocommerce-core/get-product-categories', [WooCommerceHelper::class, 'getProductCategories']);
        Route::post('woocommerce-core/get-order-status', [WooCommerceHelper::class, 'getOrderStatus']);
        Route::post('woocommerce-core/get-payment-methods', [WooCommerceHelper::class, 'getPaymentMethods']);
        Route::post('woocommerce-core/get-coupons', [WooCommerceHelper::class, 'getCoupons']);
        Route::post('woocommerce-core/get-reviews', [WooCommerceHelper::class, 'getReviews']);
        Route::post('woocommerce-core/get-pending-reviews', [WooCommerceHelper::class, 'getPendingReviews']);
        Route::post('woocommerce-core/get-attributes', [WooCommerceHelper::class, 'getAttributes']);
        Route::post('woocommerce-core/get-product-variations', [WooCommerceHelper::class, 'getProductVariations']);
    }
)->middleware('nonce:admin');
