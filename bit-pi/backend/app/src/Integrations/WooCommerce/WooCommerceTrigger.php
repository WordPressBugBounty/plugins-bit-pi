<?php

namespace BitApps\Pi\src\Integrations\WooCommerce;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Helpers\Node;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\Services\FlowService;
use BitApps\Pi\src\Flow\FlowExecutor;
use BitApps\Pi\src\Integrations\IntegrationHelper;

class WooCommerceTrigger
{
    public static function newOrderCreated($orderId, $order)
    {
        $flows = FlowService::exists('wooCommerce', 'newOrderCreated');
        if (!$flows) {
            return;
        }

        $orderData = (new WooCommerceHelper())->getOrderData($order);

        foreach ($flows as $flow) {
            $triggerNode = Node::getNodeInfoById($flow->id . '-1', $flow->nodes);
            if (!$triggerNode) {
                continue;
            }

            FlowExecutor::execute($flow, ['order_id' => $orderId, 'order' => $orderData]);
        }
    }

    // Hook did not responding
    // public static function orderUpdated($orderId, $post, $update)
    // {
    //     if (
    //         empty($orderId)
    //         || empty($post)
    //         || $post->post_status == 'trash'
    //         || !\in_array($post->post_type, ['order', 'shop_order'], true)
    //         || !$update
    //     ) {
    //         return;
    //     }

    //     return self::executeOrderTrigger($orderId, 'orderUpdated');
    // }

    // public static function orderDeleted($orderId)
    // {
    //     if (empty($orderId) || !\in_array(get_post_type($orderId), ['order', 'shop_order'], true)) {
    //         return;
    //     }

    //     return self::executeOrderTrigger($orderId, 'orderDeleted');
    // }

    public static function couponCreated($couponId, $coupon)
    {
        if (empty($couponId)) {
            return;
        }

        $couponData = $coupon->get_data();
        $formateData = [
            'coupon_id'              => $couponId,
            'coupon_code'            => $couponData['code'],
            'coupon_amount'          => $couponData['amount'],
            'coupon_status'          => $couponData['status'],
            'discount_type'          => $couponData['discount_type'],
            'description'            => $couponData['description'],
            'date_created'           => \is_null($coupon->get_date_created()) ? $coupon->get_date_created() : $coupon->get_date_created()->format('Y-m-d H:i:s'),
            'date_modified'          => \is_null($coupon->get_date_modified()) ? $coupon->get_date_modified() : $coupon->get_date_modified()->format('Y-m-d H:i:s'),
            'date_expires'           => \is_null($coupon->get_date_expires()) ? $coupon->get_date_expires() : $coupon->get_date_expires()->format('Y-m-d H:i:s'),
            'usage_count'            => $couponData['usage_count'],
            'usage_limit'            => $couponData['usage_limit'],
            'usage_limit_per_user'   => $couponData['usage_limit_per_user'],
            'limit_usage_to_x_items' => $couponData['limit_usage_to_x_items'],
            'free_shipping'          => $couponData['free_shipping'],
            'exclude_sale_items'     => $couponData['exclude_sale_items'],
            'minimum_amount'         => $couponData['minimum_amount'],
            'maximum_amount'         => $couponData['maximum_amount'],
            'virtual'                => $couponData['virtual'],
        ];

        return self::execute('couponCreated', $formateData);
    }

    public static function createCustomer($customerId, $importType)
    {
        if (empty($customerId) || empty($importType['role']) || $importType['role'] !== 'customer') {
            return false;
        }

        $customerInfo = Utility::getUserInfo($customerId);
        $customerMeta = array_map(fn ($value) => array_shift($value), get_user_meta($customerId));
        $customerData = array_merge($importType, $customerInfo, $customerMeta);

        return self::execute('createCustomer', $customerData);
    }

    public static function updateCustomer($customerId, $oldData, $newData)
    {
        if (empty($customerId) || empty($newData['role']) || $newData['role'] !== 'customer') {
            return false;
        }

        $customerData = ['customer_id' => $customerId, 'old_data' => $oldData, 'new_data' => $newData];

        return self::execute('updateCustomer', $customerData);
    }

    public static function deleteCustomer($customerId)
    {
        if (empty($customerId)) {
            return false;
        }

        $customerData = Utility::getUserInfo($customerId);
        if (!\in_array('customer', $customerData['user_roles'])) {
            return false;
        }

        return self::execute('deleteCustomer', $customerData);
    }

    public static function createProduct($postId, $post, $update, $postBefore = null)
    {
        if (!empty($postBefore) && !\in_array($postBefore->post_status, ['auto-draft', 'draft'])) {
            return false;
        }

        return self::executeProductTrigger($postId, $post, 'createProduct');
    }

    public static function updateProduct($postId, $post, $update, $postBefore = null)
    {
        if (empty($postBefore) || $postBefore->post_status === 'auto-draft') {
            return false;
        }

        return self::executeProductTrigger($postId, $post, 'updateProduct');
    }

    public static function productStatusUpdated($newStatus, $oldStatus, $post)
    {
        $additionalData = ['old_status' => $oldStatus, 'new_status' => $newStatus];

        return self::executeProductTrigger($post->ID, $post, 'productStatusUpdated', $newStatus, $additionalData);
    }

    public static function deleteProduct($newStatus, $oldStatus, $post)
    {
        if (
            $newStatus !== 'trash'
            || $oldStatus === 'new'
            || empty($post->post_type)
            || $post->post_type != 'product'
        ) {
            return false;
        }

        return self::executeProductTrigger($post->ID, $post, 'deleteProduct', 'trash');
    }

    public static function restoreProduct($newStatus, $oldStatus, $post)
    {
        if ($oldStatus != 'trash') {
            return false;
        }

        return self::executeProductTrigger($post->ID, $post, 'restoreProduct', $newStatus);
    }

    public static function productStatusChanged($newStatus, $oldStatus, $post)
    {
        return self::executeProductTrigger($post->ID, $post, 'productStatusChanged', $newStatus, ['old_status' => $oldStatus, 'new_status' => $newStatus]);
    }

    public static function productAddedToCart($cartItemKey, $productId, $quantity, $variationId, $variation, $cartItemData)
    {
        if (
            !self::isPluginInstalled()
            || empty($cartItemKey)
            || empty($productId)
        ) {
            return false;
        }

        $cart = WC()->cart;
        $cartLineItems = array_map(
            function ($cartItem) {
                return [
                    'product'            => $cartItem['data'],
                    'product_id'         => $cartItem['product_id'],
                    'variation_id'       => $cartItem['variation_id'],
                    'quantity'           => $cartItem['quantity'],
                    'product_name'       => $cartItem['data']->get_name(),
                    'tax_class'          => $cartItem['data']->get_tax_class(),
                    'tax_status'         => $cartItem['data']->get_tax_status(),
                    'product_sku'        => $cartItem['data']->get_sku(),
                    'product_unit_price' => $cartItem['data']->get_price(),
                ];
            },
            $cart->get_cart()
        );

        $cartData = [
            'cart_item_key'   => $cartItemKey,
            'product_id'      => $productId,
            'quantity'        => $quantity,
            'variation_id'    => $variationId,
            'variation'       => $variation,
            'cart_item_data'  => $cartItemData,
            'cart_total'      => $cart->get_cart_contents_total(),
            'cart_line_items' => wp_json_encode(array_values($cartLineItems)),
        ];

        return self::execute('productAddedToCart', $cartData);
    }

    public static function productRemovedFromCart($cartItemKey, $cart)
    {
        if (
            !self::isPluginInstalled()
            || empty($cartItemKey)
            || empty($cart->removed_cart_contents)
        ) {
            return false;
        }

        $cartData = [
            'cart_item_key'         => $cartItemKey,
            'applied_coupons'       => $cart->applied_coupons,
            'cart_session_data'     => $cart->cart_session_data,
            'removed_cart_contents' => array_values($cart->removed_cart_contents)
        ];

        return self::execute('productRemovedFromCart', $cartData);
    }

    public static function orderStatusPending($orderId)
    {
        return self::executeOrderTrigger($orderId, 'orderStatusPending');
    }

    public static function orderStatusFailed($orderId)
    {
        return self::executeOrderTrigger($orderId, 'orderStatusFailed');
    }

    public static function orderStatusOnHold($orderId)
    {
        return self::executeOrderTrigger($orderId, 'orderStatusOnHold');
    }

    public static function orderStatusProcessing($orderId)
    {
        return self::executeOrderTrigger($orderId, 'orderStatusProcessing');
    }

    public static function orderStatusCompleted($orderId)
    {
        return self::executeOrderTrigger($orderId, 'orderStatusCompleted');
    }

    public static function orderStatusRefunded($orderId)
    {
        return self::executeOrderTrigger($orderId, 'orderStatusRefunded');
    }

    public static function orderStatusCancelled($orderId)
    {
        return self::executeOrderTrigger($orderId, 'orderStatusCancelled');
    }

    public static function orderStatusChanged($orderId, $oldStatus, $newStatus, $order)
    {
        return self::executeOrderTrigger($orderId, 'orderStatusChanged', $order, ['old_status' => $oldStatus, 'new_status' => $newStatus]);
    }

    public static function restoreOrder($orderId, $oldStatus, $newStatus, $order)
    {
        if ($oldStatus != 'trash') {
            return;
        }

        return self::executeOrderTrigger($orderId, 'restoreOrder', $order);
    }

    // TODO:: need to implement
    public static function isPluginInstalled()
    {
        return class_exists('WooCommerce');
    }

    private static function executeOrderTrigger($orderId, $machineSlug, $order = null, $extra = [])
    {
        if (!self::isPluginInstalled() || empty($orderId)) {
            return false;
        }

        $order = empty($order) ? wc_get_order($orderId) : $order;
        if (empty($order)) {
            return;
        }

        $wcHelper = new WooCommerceHelper();
        $orderData = $wcHelper->getOrderData($order);
        $acfFieldGroups = $wcHelper->getAcfFieldGroups(['product', 'shop_order']);
        $acfFieldData = $wcHelper->getAcfFieldData($acfFieldGroups, $orderId);
        $customCheckoutData = $wcHelper->getCustomCheckoutData($order);
        $flexibleCheckoutData = $wcHelper->getFlexibleCheckoutData($order);

        return self::execute($machineSlug, array_merge($orderData, $acfFieldData, $customCheckoutData, $flexibleCheckoutData, $extra));
    }

    private static function executeProductTrigger($postId, $post, $machineSlug, $postStatus = 'publish', $extra = [])
    {
        if (
            !self::isPluginInstalled()
            || $post->post_type != 'product'
            || $post->post_status != $postStatus
        ) {
            return false;
        }

        $product = wc_get_product($postId);
        if (empty($product)) {
            return;
        }

        $wcHelper = new WooCommerceHelper();
        $productData = $wcHelper->getProductData($product);
        $acfFieldGroups = $wcHelper->getAcfFieldGroups(['product']);
        $acfFieldData = $wcHelper->getAcfFieldData($acfFieldGroups, $postId);

        return self::execute($machineSlug, array_merge($productData, $acfFieldData, $extra));
    }

    private static function execute($machineSlug, $data)
    {
        $flows = FlowService::exists('wooCommerce', $machineSlug);
        if (!$flows) {
            return;
        }

        IntegrationHelper::handleFlowForForm($flows, $data);
    }
}
