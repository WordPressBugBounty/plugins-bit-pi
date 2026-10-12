<?php

namespace BitApps\Pi\src\Integrations\WooCommerce;

use BitApps\Pi\src\Integrations\HookRegisterInterface;

if (!defined('ABSPATH')) {
    exit;
}

class WooCommerceHooks implements HookRegisterInterface
{
    public function register(): array
    {
        return [
            'newOrderCreated' => [
                'hook'          => 'woocommerce_new_order',
                'callback'      => [WooCommerceTrigger::class, 'newOrderCreated'],
                'accepted_args' => 2,
            ],
            'couponCreated' => [
                'hook'          => ['woocommerce_new_coupon', 'woocommerce_update_coupon'],
                'callback'      => [WooCommerceTrigger::class, 'couponCreated'],
                'accepted_args' => 2,
            ],
            'createCustomer' => [
                'hook'          => 'user_register',
                'callback'      => [WooCommerceTrigger::class, 'createCustomer'],
                'accepted_args' => 2,
            ],
            'updateCustomer' => [
                'hook'          => 'profile_update',
                'callback'      => [WooCommerceTrigger::class, 'updateCustomer'],
                'accepted_args' => 3,
            ],
            'deleteCustomer' => [
                'hook'     => 'delete_user',
                'callback' => [WooCommerceTrigger::class, 'deleteCustomer'],
            ],
            'createProduct' => [
                'hook'          => 'wp_after_insert_post',
                'callback'      => [WooCommerceTrigger::class, 'createProduct'],
                'accepted_args' => 4,
            ],
            'updateProduct' => [
                'hook'          => 'wp_after_insert_post',
                'callback'      => [WooCommerceTrigger::class, 'updateProduct'],
                'accepted_args' => 4,
            ],
            'productStatusUpdated' => [
                'hook'          => 'transition_post_status',
                'callback'      => [WooCommerceTrigger::class, 'productStatusUpdated'],
                'accepted_args' => 3,
            ],
            'deleteProduct' => [
                'hook'          => 'transition_post_status',
                'callback'      => [WooCommerceTrigger::class, 'deleteProduct'],
                'accepted_args' => 3,
            ],
            'restoreProduct' => [
                'hook'          => 'transition_post_status',
                'callback'      => [WooCommerceTrigger::class, 'restoreProduct'],
                'accepted_args' => 3,
            ],
            'productStatusChanged' => [
                'hook'          => 'transition_post_status',
                'callback'      => [WooCommerceTrigger::class, 'productStatusChanged'],
                'accepted_args' => 3,
            ],
            'productAddedToCart' => [
                'hook'          => 'woocommerce_add_to_cart',
                'callback'      => [WooCommerceTrigger::class, 'productAddedToCart'],
                'accepted_args' => 6,
            ],
            'productRemovedFromCart' => [
                'hook'          => 'woocommerce_cart_item_removed',
                'callback'      => [WooCommerceTrigger::class, 'productRemovedFromCart'],
                'accepted_args' => 2,
            ],
            'orderStatusPending' => [
                'hook'     => 'woocommerce_order_status_pending',
                'callback' => [WooCommerceTrigger::class, 'orderStatusPending'],
            ],
            'orderStatusFailed' => [
                'hook'     => 'woocommerce_order_status_failed',
                'callback' => [WooCommerceTrigger::class, 'orderStatusFailed'],
            ],
            'orderStatusOnHold' => [
                'hook'     => 'woocommerce_order_status_on-hold',
                'callback' => [WooCommerceTrigger::class, 'orderStatusOnHold'],
            ],
            'orderStatusProcessing' => [
                'hook'     => 'woocommerce_order_status_processing',
                'callback' => [WooCommerceTrigger::class, 'orderStatusProcessing'],
            ],
            'orderStatusCompleted' => [
                'hook'     => 'woocommerce_order_status_completed',
                'callback' => [WooCommerceTrigger::class, 'orderStatusCompleted'],
            ],
            'orderStatusRefunded' => [
                'hook'     => 'woocommerce_order_status_refunded',
                'callback' => [WooCommerceTrigger::class, 'orderStatusRefunded'],
            ],
            'orderStatusCancelled' => [
                'hook'     => 'woocommerce_order_status_cancelled',
                'callback' => [WooCommerceTrigger::class, 'orderStatusCancelled'],
            ],
            'orderStatusChanged' => [
                'hook'          => 'woocommerce_order_status_changed',
                'callback'      => [WooCommerceTrigger::class, 'orderStatusChanged'],
                'accepted_args' => 4,
            ],
            'restoreOrder' => [
                'hook'          => 'woocommerce_order_status_changed',
                'callback'      => [WooCommerceTrigger::class, 'restoreOrder'],
                'accepted_args' => 4,
            ],
            'orderPaid' => [
                'hook'     => 'woocommerce_payment_complete',
                'callback' => [WooCommerceTrigger::class, 'orderPaid'],
            ],
            'orderNoteAdded' => [
                'hook'          => 'woocommerce_order_note_added',
                'callback'      => [WooCommerceTrigger::class, 'orderNoteAdded'],
                'accepted_args' => 2,
            ],
            'variableProductPurchased' => [
                'hook'     => 'woocommerce_payment_complete',
                'callback' => [WooCommerceTrigger::class, 'variableProductPurchased'],
            ],
            'productCategoryPurchased' => [
                'hook'     => 'woocommerce_payment_complete',
                'callback' => [WooCommerceTrigger::class, 'productCategoryPurchased'],
            ],
            'reviewAdded' => [
                'hook'          => 'comment_post',
                'callback'      => [WooCommerceTrigger::class, 'reviewAdded'],
                'accepted_args' => 3,
            ],
            'reviewApproved' => [
                'hook'          => 'transition_comment_status',
                'callback'      => [WooCommerceTrigger::class, 'reviewApproved'],
                'accepted_args' => 3,
            ],
            'reviewStatusChanged' => [
                'hook'          => 'transition_comment_status',
                'callback'      => [WooCommerceTrigger::class, 'reviewStatusChanged'],
                'accepted_args' => 3,
            ],
            'reviewRatingUpdated' => [
                'hook'          => 'updated_comment_meta',
                'callback'      => [WooCommerceTrigger::class, 'reviewRatingUpdated'],
                'accepted_args' => 4,
            ],
            'reviewDeleted' => [
                'hook'          => 'delete_comment',
                'callback'      => [WooCommerceTrigger::class, 'reviewDeleted'],
                'accepted_args' => 2,
            ],
            'productRefunded' => [
                'hook'          => 'woocommerce_order_refunded',
                'callback'      => [WooCommerceTrigger::class, 'productRefunded'],
                'accepted_args' => 2,
            ],
            'productViewed' => [
                'hook'     => 'template_redirect',
                'callback' => [WooCommerceTrigger::class, 'productViewed'],
            ],
            'productOutOfStock' => [
                'hook'     => 'woocommerce_no_stock',
                'callback' => [WooCommerceTrigger::class, 'productOutOfStock'],
            ],
            'productBackInStock' => [
                'hook'          => 'woocommerce_product_set_stock_status',
                'callback'      => [WooCommerceTrigger::class, 'productBackInStock'],
                'accepted_args' => 3,
            ],
            'productLowStock' => [
                'hook'     => 'woocommerce_low_stock',
                'callback' => [WooCommerceTrigger::class, 'productLowStock'],
            ],
            'variationOutOfStock' => [
                'hook'          => 'woocommerce_variation_set_stock_status',
                'callback'      => [WooCommerceTrigger::class, 'variationOutOfStock'],
                'accepted_args' => 3,
            ],
            'variationBackInStock' => [
                'hook'          => 'woocommerce_variation_set_stock_status',
                'callback'      => [WooCommerceTrigger::class, 'variationBackInStock'],
                'accepted_args' => 3,
            ],
            'couponUpdated' => [
                'hook'          => 'woocommerce_update_coupon',
                'callback'      => [WooCommerceTrigger::class, 'couponUpdated'],
                'accepted_args' => 2,
            ],
            'couponDeleted' => [
                'hook'          => 'wp_trash_post',
                'callback'      => [WooCommerceTrigger::class, 'couponDeleted'],
                'accepted_args' => 2,
            ],
            'couponRestored' => [
                'hook'          => 'untrashed_post',
                'callback'      => [WooCommerceTrigger::class, 'couponRestored'],
                'accepted_args' => 2,
            ],
        ];
    }
}
