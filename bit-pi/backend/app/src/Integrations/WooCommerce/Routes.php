<?php

if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Router\Route;
use BitApps\Pi\src\Integrations\WooCommerce\WooCommerce;

Route::group(
    function () {
        Route::post('woocommerce-core/get-post-types', [WooCommerce::class, 'getPostTypes']);
        Route::post('woocommerce-core/get-posts', [WooCommerce::class, 'getPosts']);
        Route::post('woocommerce-core/get-product-category', [WooCommerce::class, 'getProductCategory']);
        Route::post('woocommerce-core/get-products', [WooCommerce::class, 'getProducts']);
        Route::post('woocommerce-core/get-shipping-class', [WooCommerce::class, 'getShippingClass']);
        Route::post('woocommerce-core/get-product-tags', [WooCommerce::class, 'getProductTags']);
        Route::post('woocommerce-core/get-product-brands', [WooCommerce::class, 'getProductBrands']);
        Route::post('woocommerce-core/get-product-categories', [WooCommerce::class, 'getProductCategories']);
        Route::post('woocommerce-core/get-order-status', [WooCommerce::class, 'getOrderStatus']);
        Route::post('woocommerce-core/get-payment-methods', [WooCommerce::class, 'getPaymentMethods']);
        Route::post('woocommerce-core/get-coupons', [WooCommerce::class, 'getCoupons']);
    }
)->middleware('nonce:admin');
