<?php

namespace BitApps\Pi\src\Integrations\WooCommerce;

use BitApps\Pi\Deps\BitApps\WPValidator\Validator;
use WC_Coupon;
use WC_Product;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

class WooCommerceActionHelper
{
    /**
     * Get orders.
     *
     * @param null|array|string $status
     * @param null|int $customerId
     * @param null|string $billingEmail
     * @param null|array|string $type
     */
    public static function getOrders($status = null, $customerId = null, $billingEmail = null, $type = null): array
    {
        $args = [
            'limit'   => -1,
            'orderby' => 'date',
            'order'   => 'DESC',
        ];

        if (isset($status)) {
            $args['status'] = $status;
        }

        if (isset($billingEmail)) {
            $args['billing_email'] = $billingEmail;
        }

        if (isset($customerId)) {
            $args['customer_id'] = $customerId;
        }

        if (isset($type)) {
            $args['type'] = $type;
        }

        return array_map(
            function ($order) {
                return $order->get_data();
            },
            wc_get_orders($args)
        );
    }

    /**
     * Get products.
     *
     * @param null|array|string $type
     * @param null|array|string $category
     */
    public static function getProducts($type = null, $category = null): array
    {
        $args = [
            'limit'  => -1,
            'return' => 'objects',
        ];

        if (isset($type)) {
            $args['type'] = $type;
        }

        if (isset($category)) {
            $args['category'] = $category;
        }

        return array_map(
            function ($product) {
                return $product->get_data();
            },
            wc_get_products($args)
        );
    }

    /**
     * Get product by id.
     *
     * @param int $id
     */
    public static function getProductById($id)
    {
        return wc_get_product($id)->get_data();
    }

    /**
     * Validate field map.
     *
     * @param array $fieldMappings
     * @param array $validationRules
     * @param null|array $payload
     */
    public static function validateFieldMap($fieldMappings, $validationRules, $payload = null)
    {
        $validator = new Validator();

        $validation = $validator->make($fieldMappings, $validationRules);

        if ($validation->fails()) {
            return [
                'response'    => $validation->errors(),
                'payload'     => $payload ?? $fieldMappings,
                'status_code' => 404
            ];
        }
    }

    /**
     * Check if WooCommerce is installed & active.
     */
    public static function ensureWooCommerceActive()
    {
        if (!WooCommerceHelper::isPluginInstalled()) {
            return [
                'status_code' => 422,
                'response'    => __('WooCommerce plugin is not installed or activated', 'bit-pi'),
                'payload'     => [],
            ];
        }
    }

    /**
     * Get product order status count.
     *
     * @param WC_Product $product
     *
     * @return array
     */
    public static function getProductOrderStatusCount($product)
    {
        if (!$product instanceof WC_Product) {
            return [];
        }

        $productId = $product->get_id();
        // $report = [
        //     'total_sales' => $product->get_total_sales()
        // ];

        $statuses = [
            'wc-pending'    => 'total_orders_on_pending',
            'wc-processing' => 'total_orders_on_processing',
            'wc-on-hold'    => 'total_orders_on_hold',
            'wc-cancelled'  => 'total_orders_cancelled',
            'wc-completed'  => 'total_orders_completed',
            'wc-refunded'   => 'total_orders_refunded',
            'wc-failed'     => 'total_orders_failed',
        ];

        $report = array_fill_keys($statuses, 0);

        // Fetch all orders with relevant statuses in a single query
        $all_orders = wc_get_orders(
            [
                'status' => array_keys($statuses),
                'limit'  => -1,
                'return' => 'objects',
            ]
        );

        foreach ($all_orders as $order) {
            $status = $order->get_status();

            if (!isset($statuses["wc-{$status}"])) {
                continue;
            }

            foreach ($order->get_items() as $item) {
                if ((int) $item->get_product_id() === (int) $productId) {
                    ++$report[$statuses["wc-{$status}"]];

                    break;
                }
            }
        }

        return $report;
    }

    /**
     * Get Terms.
     *
     * @param string $taxonomy
     * @param int $termId
     *
     * @return array
     */
    public static function getTerms($taxonomy = null, $termId = null)
    {
        if ($termId) {
            return get_term($termId, $taxonomy);
        }

        $args = [
            'orderby'    => 'term_id',
            'hide_empty' => false
        ];

        if (!empty($taxonomy)) {
            $args['taxonomy'] = $taxonomy;
        }

        return get_terms($args);
    }

    /**
     * Get coupon data.
     *
     * @param int $id
     */
    public static function getCouponData($id)
    {
        $coupon = new WC_Coupon($id);

        return $coupon->get_data();
    }

    /**
     * Sanitize customer email addresses.
     *
     * @param string $emails
     */
    public static function sanitizeCustomerEmails($emails)
    {
        return array_filter(
            array_map(
                function ($email) {
                    return sanitize_email(trim($email));
                },
                explode(',', $emails)
            )
        );
    }

    /**
     * Format coupon boolean fields.
     *
     * @param array $fieldMappings
     *
     * @return array
     */
    public static function formatCouponBooleanFields($fieldMappings)
    {
        $booleanFields = [
            'individual_use',
            'exclude_sale_items',
            'free_shipping',
        ];

        foreach ($booleanFields as $value) {
            if (isset($fieldMappings[$value])) {
                $fieldMappings[$value] = empty($fieldMappings[$value]) ? 'no' : 'yes';
            }
        }

        return $fieldMappings;
    }

    /**
     * Update product metadata.
     *
     * @param array $fieldMappings
     * @param int $postId
     */
    public static function updateProductMetadata($fieldMappings, $postId)
    {
        $metaKeysMapping = [
            '_visibility'        => 'catalog_visibility',
            '_stock_status'      => 'stock_status',
            'catalog_visibility' => 'catalog_visibility',
            '_sku'               => 'sku',
            '_manage_stock'      => 'manage_stock',
            '_stock'             => 'stock_quantity',
            '_product_url'       => 'external_url',
            '_sold_individually' => 'sold_individually',
        ];

        foreach ($metaKeysMapping as $metaKey => $fieldKey) {
            if (isset($fieldMappings[$fieldKey])) {
                update_post_meta($postId, $metaKey, $fieldMappings[$fieldKey]);
            }
        }
    }

    /**
     * Update product properties.
     *
     * @param array $fieldMappings
     * @param WC_Product $product
     * @param array $propsKeys
     *
     * @return WC_Product
     */
    public static function updateProductProperties($fieldMappings, $product, $propsKeys = [])
    {
        if (empty($fieldMappings['virtual'])) {
            $propsKeys = array_merge($propsKeys, ['weight', 'length', 'width', 'height']);
        }

        if ($fieldMappings['sale_schedule'] ?? false) {
            $propsKeys = array_merge($propsKeys, ['date_on_sale_from', 'date_on_sale_to']);
        }

        if ($fieldMappings['manage_stock'] ?? false) {
            $propsKeys = array_merge($propsKeys, ['stock_quantity', 'low_stock_amount', 'stock_status', 'backorders']);
        }

        $props = [];

        foreach ($propsKeys as $key) {
            if ($key === 'price' && isset($fieldMappings['regular_price'])) {
                $props[$key] = wc_format_decimal($fieldMappings['regular_price']);
            } elseif (isset($fieldMappings[$key])) {
                $props[$key] = \in_array($key, ['regular_price', 'sale_price'])
                    ? wc_format_decimal($fieldMappings[$key] ?? 0)
                    : $fieldMappings[$key];
            }
        }

        if (empty($props)) {
            return $product;
        }

        $product->set_props($props);
        $product->save();

        return $product;
    }

    /**
     * Set product terms.
     *
     * @param array $fieldMappings
     * @param int $productId
     */
    public static function setProductTerms($fieldMappings, $productId)
    {
        if (!empty($fieldMappings['category_ids'])) {
            wp_set_object_terms($productId, $fieldMappings['category_ids'], 'product_cat');
        }

        if (!empty($fieldMappings['tag_ids'])) {
            wp_set_object_terms($productId, $fieldMappings['tag_ids'], 'product_tag');
        }

        if (!empty($fieldMappings['brand_ids'])) {
            wp_set_object_terms($productId, $fieldMappings['brand_ids'], 'product_brand');
        }
    }

    /**
     * Upload product image.
     *
     * @param string $imageUrl
     * @param int $postId
     * @param array $fieldMappings
     *
     * @return array|int
     */
    public static function uploadProductImage($imageUrl, $postId, $fieldMappings)
    {
        $imageRequest = wp_remote_get($imageUrl);

        if (is_wp_error($imageRequest)) {
            return [
                'response'    => $imageRequest->get_error_message(),
                'payload'     => $fieldMappings,
                'status_code' => $imageRequest->get_error_code(),
            ];
        }

        $imageContent = wp_remote_retrieve_body($imageRequest);

        $uploadResult = wp_upload_bits(basename($imageUrl), null, $imageContent);

        if ($uploadResult['error']) {
            return [
                'response'    => __('Image upload failed: ', 'bit-pi') . $uploadResult['error'],
                'payload'     => $fieldMappings,
                'status_code' => 422,
            ];
        }

        $uploadedFilePath = $uploadResult['file'];
        $wpFiletype = wp_check_filetype($uploadedFilePath, null);

        $attachmentData = [
            'post_mime_type' => $wpFiletype['type'],
            'post_title'     => sanitize_file_name($uploadedFilePath),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachmentId = wp_insert_attachment($attachmentData, $uploadedFilePath, $postId);

        if (is_wp_error($attachmentId)) {
            return [
                'response'    => $attachmentId->get_error_message(),
                'payload'     => $fieldMappings,
                'status_code' => $attachmentId->get_error_code(),
            ];
        }

        include_once ABSPATH . 'wp-admin/includes/image.php';

        $attachmentMeta = wp_generate_attachment_metadata($attachmentId, $uploadedFilePath);

        wp_update_attachment_metadata($attachmentId, $attachmentMeta);

        return $attachmentId;
    }

    public static function getVariationAttachmentId($variationImage, $variationId)
    {
        if (!\function_exists('media_handle_upload')) {
            include_once ABSPATH . 'wp-admin/includes/media.php';
        }

        if (!\function_exists('wp_handle_upload')) {
            include_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if (!\function_exists('wp_generate_attachment_metadata')) {
            include_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $existingMediaId = attachment_url_to_postid($variationImage);

        if ($existingMediaId) {
            return $existingMediaId;
        }

        $attachmentId = media_sideload_image($variationImage, $variationId, null, 'id');

        if (is_wp_error($attachmentId)) {
            return;
        }

        return $attachmentId;
    }
}
