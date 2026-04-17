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
                'priority'      => 10,
                'accepted_args' => 2,
            ],
            // 'orderUpdated' => [
            //     'hook'          => ['save_post', 'save_post_shop_order'],
            //     'callback'      => [WooCommerceTrigger::class, 'orderUpdated'],
            //     'priority'      => 10,
            //     'accepted_args' => 3,
            // ],
            // 'orderDeleted' => [
            //     'hook'          => ['wp_trash_post', 'wp_delete_post', 'untrashed_post'],
            //     'callback'      => [WooCommerceTrigger::class, 'orderDeleted'],
            //     'priority'      => 10,
            //     'accepted_args' => 1,
            // ],
            'couponCreated' => [
                'hook'          => 'woocommerce_update_coupon',
                'callback'      => [WooCommerceTrigger::class, 'couponCreated'],
                'priority'      => 10,
                'accepted_args' => 2,
            ],
            'createCustomer' => [
                'hook'          => 'user_register',
                'callback'      => [WooCommerceTrigger::class, 'createCustomer'],
                'priority'      => 10,
                'accepted_args' => 2,
            ],
            'updateCustomer' => [
                'hook'          => 'profile_update',
                'callback'      => [WooCommerceTrigger::class, 'updateCustomer'],
                'priority'      => 10,
                'accepted_args' => 3,
            ],
            'deleteCustomer' => [
                'hook'          => 'delete_user',
                'callback'      => [WooCommerceTrigger::class, 'deleteCustomer'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'createProduct' => [
                'hook'          => 'wp_after_insert_post',
                'callback'      => [WooCommerceTrigger::class, 'createProduct'],
                'priority'      => 10,
                'accepted_args' => 4,
            ],
            'updateProduct' => [
                'hook'          => 'wp_after_insert_post',
                'callback'      => [WooCommerceTrigger::class, 'updateProduct'],
                'priority'      => 10,
                'accepted_args' => 4,
            ],
            'productStatusUpdated' => [
                'hook'          => 'transition_post_status',
                'callback'      => [WooCommerceTrigger::class, 'productStatusUpdated'],
                'priority'      => 10,
                'accepted_args' => 3,
            ],
            'deleteProduct' => [
                'hook'          => 'transition_post_status',
                'callback'      => [WooCommerceTrigger::class, 'deleteProduct'],
                'priority'      => 10,
                'accepted_args' => 3,
            ],
            'restoreProduct' => [
                'hook'          => 'transition_post_status',
                'callback'      => [WooCommerceTrigger::class, 'restoreProduct'],
                'priority'      => 10,
                'accepted_args' => 3,
            ],
            'productStatusChanged' => [
                'hook'          => 'transition_post_status',
                'callback'      => [WooCommerceTrigger::class, 'productStatusChanged'],
                'priority'      => 10,
                'accepted_args' => 3,
            ],
            'productAddedToCart' => [
                'hook'          => 'woocommerce_add_to_cart',
                'callback'      => [WooCommerceTrigger::class, 'productAddedToCart'],
                'priority'      => 10,
                'accepted_args' => 6,
            ],
            'productRemovedFromCart' => [
                'hook'          => 'woocommerce_cart_item_removed',
                'callback'      => [WooCommerceTrigger::class, 'productRemovedFromCart'],
                'priority'      => 10,
                'accepted_args' => 2,
            ],
            'orderStatusPending' => [
                'hook'          => 'woocommerce_order_status_pending',
                'callback'      => [WooCommerceTrigger::class, 'orderStatusPending'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'orderStatusFailed' => [
                'hook'          => 'woocommerce_order_status_failed',
                'callback'      => [WooCommerceTrigger::class, 'orderStatusFailed'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'orderStatusOnHold' => [
                'hook'          => 'woocommerce_order_status_on-hold',
                'callback'      => [WooCommerceTrigger::class, 'orderStatusOnHold'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'orderStatusProcessing' => [
                'hook'          => 'woocommerce_order_status_processing',
                'callback'      => [WooCommerceTrigger::class, 'orderStatusProcessing'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'orderStatusCompleted' => [
                'hook'          => 'woocommerce_order_status_completed',
                'callback'      => [WooCommerceTrigger::class, 'orderStatusCompleted'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'orderStatusRefunded' => [
                'hook'          => 'woocommerce_order_status_refunded',
                'callback'      => [WooCommerceTrigger::class, 'orderStatusRefunded'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'orderStatusCancelled' => [
                'hook'          => 'woocommerce_order_status_cancelled',
                'callback'      => [WooCommerceTrigger::class, 'orderStatusCancelled'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'orderStatusChanged' => [
                'hook'          => 'woocommerce_order_status_changed',
                'callback'      => [WooCommerceTrigger::class, 'orderStatusChanged'],
                'priority'      => 10,
                'accepted_args' => 4,
            ],
            'restoreOrder' => [
                'hook'          => 'woocommerce_order_status_changed',
                'callback'      => [WooCommerceTrigger::class, 'restoreOrder'],
                'priority'      => 10,
                'accepted_args' => 4,
            ],
        ];
    }
}
