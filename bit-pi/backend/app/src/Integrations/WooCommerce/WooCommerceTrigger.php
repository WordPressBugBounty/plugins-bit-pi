<?php

namespace BitApps\Pi\src\Integrations\WooCommerce;

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\Services\FlowService;
use BitApps\Pi\src\Integrations\IntegrationHelper;
use BitApps\Pi\src\Integrations\WooCommerce\helper\WooCommerceHelper;
use WC_Coupon;
use WC_Order;
use WC_Product_Variation;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

final class WooCommerceTrigger
{
    public static function newOrderCreated($orderId, $order)
    {
        return self::execute('newOrderCreated', ['order_id' => $orderId, 'order' => WooCommerceHelper::getOrderData($order)]);
    }

    public static function couponCreated($couponId, $coupon)
    {
        if (!self::isCouponJustCreated($couponId)) {
            return;
        }

        return self::executeCouponTrigger($couponId, $coupon, 'couponCreated');
    }

    public static function createCustomer($customerId, $importType)
    {
        if (empty($customerId) || empty($importType['role']) || $importType['role'] !== 'customer') {
            return false;
        }

        $customerInfo = Utility::getUserInfo($customerId);
        $customerMeta = array_map(fn ($value) => array_shift($value), get_user_meta($customerId));
        $customerData = self::removeSensitiveData(array_merge($importType, $customerInfo, $customerMeta));

        return self::execute('createCustomer', $customerData);
    }

    public static function updateCustomer($customerId, $oldData, $newData)
    {
        if (empty($customerId) || empty($newData['role']) || $newData['role'] !== 'customer') {
            return false;
        }

        $customerData = [
            'customer_id' => $customerId,
            'old_data'    => self::removeSensitiveData($oldData),
            'new_data'    => self::removeSensitiveData($newData),
        ];

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
            !WooCommerceHelper::isPluginInstalled()
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
            !WooCommerceHelper::isPluginInstalled()
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

    public static function orderPaid($orderId)
    {
        return self::executeOrderTrigger($orderId, 'orderPaid');
    }

    public static function orderNoteAdded($noteId, $order)
    {
        if (!WooCommerceHelper::isPluginInstalled() || empty($noteId) || !($order instanceof WC_Order)) {
            return;
        }

        $noteData = wc_get_order_note($noteId);
        if (empty($noteData)) {
            return;
        }

        $noteData->date_created = WooCommerceHelper::formatDate($noteData->date_created);
        $noteData->note_type = $noteData->customer_note ? 'customer' : 'internal';

        $data = [
            'note'  => $noteData,
            'order' => WooCommerceHelper::getOrderData($order),
        ];

        return self::execute('orderNoteAdded', $data);
    }

    public static function variableProductPurchased($orderId)
    {
        if (!WooCommerceHelper::isPluginInstalled() || empty($orderId)) {
            return;
        }

        $order = wc_get_order($orderId);

        if (empty($order)) {
            return;
        }

        $orderData = WooCommerceHelper::getOrderData($order);
        $variableProducts = [];

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            if (!$product) {
                continue;
            }

            $parentProduct = wc_get_product($item->get_product_id());

            if ($parentProduct && $parentProduct->is_type('variable')) {
                $variableProducts[] = [
                    'product_id'   => $item->get_product_id(),
                    'variation_id' => $item->get_variation_id(),
                    'product_name' => $item->get_name(),
                    'quantity'     => $item->get_quantity(),
                    'product_data' => $product->get_data(),
                ];
            }
        }

        if (empty($variableProducts)) {
            return;
        }

        $data = array_merge($orderData, ['variable_products' => wp_json_encode($variableProducts)]);

        return self::execute('variableProductPurchased', $data);
    }

    public static function productCategoryPurchased($orderId)
    {
        if (!WooCommerceHelper::isPluginInstalled() || empty($orderId)) {
            return;
        }

        $order = wc_get_order($orderId);

        if (empty($order)) {
            return;
        }

        $orderData = WooCommerceHelper::getOrderData($order);

        $purchasedCategories = [];

        foreach ($order->get_items() as $item) {
            $productId = $item->get_product_id();
            $categories = wp_get_post_terms($productId, 'product_cat', ['fields' => 'all']);

            if (is_wp_error($categories)) {
                continue;
            }

            foreach ($categories as $cat) {
                if (!isset($purchasedCategories[$cat->term_id])) {
                    $purchasedCategories[$cat->term_id] = [
                        'category_id'   => $cat->term_id,
                        'category_name' => $cat->name,
                        'category_slug' => $cat->slug,
                        'quantity'      => 0,
                        'products'      => [],
                    ];
                }

                $purchasedCategories[$cat->term_id]['quantity'] += $item->get_quantity();
                $purchasedCategories[$cat->term_id]['products'][] = [
                    'product_id'   => $productId,
                    'product_name' => $item->get_name(),
                    'quantity'     => $item->get_quantity(),
                ];
            }
        }

        if (empty($purchasedCategories)) {
            return;
        }

        $data = array_merge($orderData, ['purchased_categories' => wp_json_encode(array_values($purchasedCategories))]);

        return self::execute('productCategoryPurchased', $data);
    }

    public static function reviewAdded($commentId, $commentApproved)
    {
        if (\in_array($commentApproved, ['spam', 'trash'], true)) {
            return;
        }

        return self::executeReviewTrigger('reviewAdded', get_comment($commentId), ['approved' => $commentApproved]);
    }

    public static function reviewApproved($newStatus, $oldStatus, $comment)
    {
        if ($newStatus !== 'approved') {
            return;
        }

        return self::executeReviewTrigger('reviewApproved', $comment, ['old_status' => $oldStatus, 'new_status' => $newStatus]);
    }

    public static function reviewStatusChanged($newStatus, $oldStatus, $comment)
    {
        return self::executeReviewTrigger('reviewStatusChanged', $comment, ['old_status' => $oldStatus, 'new_status' => $newStatus]);
    }

    public static function reviewRatingUpdated($metaId, $commentId, $metaKey)
    {
        if ($metaKey !== 'rating') {
            return;
        }

        return self::executeReviewTrigger('reviewRatingUpdated', get_comment($commentId));
    }

    public static function reviewDeleted($commentId, $comment)
    {
        return self::executeReviewTrigger('reviewDeleted', $comment);
    }

    public static function productRefunded($orderId, $refundId)
    {
        if (!WooCommerceHelper::isPluginInstalled() || empty($refundId) || empty($orderId)) {
            return;
        }

        $refund = wc_get_order($refundId);
        $order = wc_get_order($orderId);

        if (empty($refund) || empty($order)) {
            return;
        }

        $orderData = WooCommerceHelper::getOrderData($order);
        $refundData = $refund->get_data();

        $refundData['date_created'] = WooCommerceHelper::formatDate($refundData['date_created']);
        $refundData['date_modified'] = WooCommerceHelper::formatDate($refundData['date_modified']);

        foreach ($refund->get_items() as $item) {
            $product = $item->get_product();
            $refundData['refunded_items'][] = [
                'product_id'   => $item->get_product_id(),
                'product_name' => $product ? $product->get_name() : '',
                'quantity'     => abs($item->get_quantity()),
                'refund_total' => abs($item->get_total()),
            ];
        }

        $data = [
            'order'  => $orderData,
            'refund' => $refundData
        ];

        return self::execute('productRefunded', $data);
    }

    public static function productViewed()
    {
        if (!WooCommerceHelper::isPluginInstalled() || !is_product() || self::isBotRequest()) {
            return;
        }

        $product = wc_get_product(get_queried_object_id());

        if (empty($product)) {
            return;
        }

        return self::execute('productViewed', WooCommerceHelper::getProductData($product));
    }

    public static function productOutOfStock($product)
    {
        if (!WooCommerceHelper::isPluginInstalled() || empty($product)) {
            return;
        }

        return self::execute('productOutOfStock', WooCommerceHelper::getProductData($product));
    }

    public static function productBackInStock($productId, $stockStatus, $product)
    {
        if (
            !WooCommerceHelper::isPluginInstalled()
            || empty($product)
            || $stockStatus !== 'instock'
            || self::getPreviousStockStatus($product) !== 'outofstock'
        ) {
            return;
        }

        return self::execute('productBackInStock', WooCommerceHelper::getProductData($product));
    }

    public static function productLowStock($product)
    {
        if (!WooCommerceHelper::isPluginInstalled() || empty($product)) {
            return;
        }

        $productData = WooCommerceHelper::getProductData($product);
        $productData['stock_quantity'] = $product->get_stock_quantity();

        return self::execute('productLowStock', $productData);
    }

    public static function variationOutOfStock($variationId, $stockStatus, $variation)
    {
        if (
            !WooCommerceHelper::isPluginInstalled()
            || !($variation instanceof WC_Product_Variation)
            || $stockStatus !== 'outofstock'
            || self::getPreviousStockStatus($variation) === 'outofstock'
        ) {
            return;
        }

        return self::execute('variationOutOfStock', self::getVariationStockData($variation, $stockStatus));
    }

    public static function variationBackInStock($variationId, $stockStatus, $variation)
    {
        if (
            !WooCommerceHelper::isPluginInstalled()
            || !($variation instanceof WC_Product_Variation)
            || $stockStatus !== 'instock'
            || self::getPreviousStockStatus($variation) !== 'outofstock'
        ) {
            return;
        }

        return self::execute('variationBackInStock', self::getVariationStockData($variation, $stockStatus));
    }

    public static function couponUpdated($couponId, $coupon)
    {
        if (self::isCouponJustCreated($couponId)) {
            return;
        }

        return self::executeCouponTrigger($couponId, $coupon, 'couponUpdated');
    }

    public static function couponDeleted($postId, $previousStatus = null)
    {
        if (!WooCommerceHelper::isPluginInstalled() || get_post_type($postId) !== 'shop_coupon') {
            return;
        }

        return self::executeCouponTrigger(
            $postId,
            new WC_Coupon($postId),
            'couponDeleted',
            ['previous_status' => $previousStatus ?? get_post_status($postId)]
        );
    }

    public static function couponRestored($postId, $previousStatus)
    {
        if (!WooCommerceHelper::isPluginInstalled() || get_post_type($postId) !== 'shop_coupon') {
            return;
        }

        return self::executeCouponTrigger(
            $postId,
            new WC_Coupon($postId),
            'couponRestored',
            ['previous_status' => $previousStatus]
        );
    }

    private static function executeOrderTrigger($orderId, $machineSlug, $order = null, $extra = [])
    {
        if (!WooCommerceHelper::isPluginInstalled() || empty($orderId)) {
            return false;
        }

        $order = empty($order) ? wc_get_order($orderId) : $order;
        if (empty($order)) {
            return;
        }

        $orderData = WooCommerceHelper::getOrderData($order);
        $acfFieldGroups = WooCommerceHelper::getAcfFieldGroups(['product', 'shop_order']);
        $acfFieldData = WooCommerceHelper::getAcfFieldData($acfFieldGroups, $orderId);
        $customCheckoutData = WooCommerceHelper::getCustomCheckoutData($order);
        $flexibleCheckoutData = WooCommerceHelper::getFlexibleCheckoutData($order);

        return self::execute($machineSlug, array_merge($orderData, $acfFieldData, $customCheckoutData, $flexibleCheckoutData, $extra));
    }

    private static function executeProductTrigger($postId, $post, $machineSlug, $postStatus = 'publish', $extra = [])
    {
        if (
            !WooCommerceHelper::isPluginInstalled()
            || $post->post_type != 'product'
            || $post->post_status != $postStatus
        ) {
            return false;
        }

        $product = wc_get_product($postId);
        if (empty($product)) {
            return;
        }

        $productData = WooCommerceHelper::getProductData($product);
        $acfFieldGroups = WooCommerceHelper::getAcfFieldGroups(['product']);
        $acfFieldData = WooCommerceHelper::getAcfFieldData($acfFieldGroups, $postId);

        return self::execute($machineSlug, array_merge($productData, $acfFieldData, $extra));
    }

    private static function executeReviewTrigger($machineSlug, $comment, $extra = [])
    {
        if (!WooCommerceHelper::isPluginInstalled() || empty($comment) || (int) $comment->comment_parent !== 0) {
            return;
        }

        $reviewData = WooCommerceHelper::getReviewData($comment);

        if (empty($reviewData)) {
            return;
        }

        return self::execute($machineSlug, array_merge($reviewData, $extra));
    }

    private static function executeCouponTrigger($couponId, $coupon, $machineSlug, $additionalData = [])
    {
        if (empty($couponId)) {
            return;
        }

        $data = $coupon->get_data();

        $data['coupon_id'] = $couponId;
        $data['date_created'] = WooCommerceHelper::formatDate($coupon->get_date_created());
        $data['date_modified'] = WooCommerceHelper::formatDate($coupon->get_date_modified());
        $data['date_expires'] = WooCommerceHelper::formatDate($coupon->get_date_expires());
        $data['coupon_code'] = $data['code'];
        $data['coupon_amount'] = $data['amount'];
        $data['coupon_status'] = $data['status'];

        return self::execute($machineSlug, array_merge($data, $additionalData));
    }

    private static function getVariationStockData($variation, $stockStatus)
    {
        $parentProduct = wc_get_product($variation->get_parent_id());

        return [
            'variation_id'   => $variation->get_id(),
            'parent_id'      => $variation->get_parent_id(),
            'parent_name'    => $parentProduct ? $parentProduct->get_name() : '',
            'sku'            => $variation->get_sku(),
            'attributes'     => $variation->get_variation_attributes(),
            'stock_quantity' => $variation->get_stock_quantity(),
            'stock_status'   => $stockStatus,
        ];
    }

    private static function getPreviousStockStatus($product)
    {
        // WooCommerce fires the stock status hooks before apply_changes(), so get_data() still holds the stored status.
        return $product->get_data()['stock_status'] ?? '';
    }

    private static function isCouponJustCreated($couponId)
    {
        $post = get_post($couponId);

        // Same 10-second window WooCommerce webhooks use to tell a coupon create from an update.
        return $post && (time() - 10) <= strtotime(get_gmt_from_date($post->post_date));
    }

    private static function isBotRequest()
    {
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? wp_unslash($_SERVER['HTTP_USER_AGENT']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        return $userAgent === '' || preg_match('/bot|crawl|spider|slurp/i', $userAgent) === 1;
    }

    /**
     * Strip sensitive credentials (passwords, hashes, session/app tokens) from
     * user payloads before they are exposed as mappable trigger fields.
     *
     * @param array|WP_User $data
     *
     * @return array|WP_User
     */
    private static function removeSensitiveData($data)
    {
        $sensitiveKeys = [
            'user_pass',
            'user_activation_key',
            'session_tokens',
            'application_passwords',
        ];

        if ($data instanceof WP_User) {
            $cloned = clone $data;

            if (isset($cloned->data) && \is_object($cloned->data)) {
                $cloned->data = clone $cloned->data;
            }

            foreach ($sensitiveKeys as $key) {
                if (isset($cloned->data->{$key})) {
                    unset($cloned->data->{$key});
                }
            }

            return $cloned;
        }

        if (!\is_array($data)) {
            return $data;
        }

        foreach ($sensitiveKeys as $key) {
            unset($data[$key]);
        }

        return $data;
    }

    private static function execute($machineSlug, $data)
    {
        $flows = FlowService::exists('wooCommerce', $machineSlug);

        if (!$flows || empty($data)) {
            return;
        }

        IntegrationHelper::handleFlowForForm($flows, $data);
    }
}
