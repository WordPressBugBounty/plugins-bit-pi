<?php

namespace BitApps\Pi\src\Integrations\WooCommerce;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


class WooCommerceHelper
{
    public function getOrderData($order)
    {
        // get order data
        $orderData = [
            'id'                          => $order->get_id(),
            'order_key'                   => $order->get_order_key(),
            'card_tax'                    => $order->get_cart_tax(),
            'currency'                    => $order->get_currency(),
            'discount_tax'                => $order->get_discount_tax(),
            'discount_to_display'         => $order->get_discount_to_display(),
            'discount_total'              => $order->get_discount_total(),
            'fees'                        => $order->get_fees(),
            'shipping_tax'                => $order->get_shipping_tax(),
            'shipping_total'              => $order->get_shipping_total(),
            'tax_totals'                  => $order->get_tax_totals(),
            'total'                       => $order->get_total(),
            'total_refunded'              => $order->get_total_refunded(),
            'total_tax_refunded'          => $order->get_total_tax_refunded(),
            'total_shipping_refunded'     => $order->get_total_shipping_refunded(),
            'total_qty_refunded'          => $order->get_total_qty_refunded(),
            'remaining_refund_amount'     => $order->get_remaining_refund_amount(),
            'shipping_method'             => $order->get_shipping_method(),
            'date_created'                => \is_null($order->get_date_created()) ? $order->get_date_created() : $order->get_date_created()->format('Y-m-d H:i:s'),
            'date_modified'               => \is_null($order->get_date_modified()) ? $order->get_date_modified() : $order->get_date_modified()->format('Y-m-d H:i:s'),
            'date_completed'              => \is_null($order->get_date_completed()) ? $order->get_date_completed() : $order->get_date_completed()->format('Y-m-d H:i:s'),
            'date_paid'                   => \is_null($order->get_date_paid()) ? $order->get_date_paid() : $order->get_date_paid()->format('Y-m-d H:i:s'),
            'customer_id'                 => $order->get_customer_id(),
            'created_via'                 => $order->get_created_via(),
            'billing_first_name'          => $order->get_billing_first_name(),
            'billing_last_name'           => $order->get_billing_last_name(),
            'billing_company'             => $order->get_billing_company(),
            'billing_address_1'           => $order->get_billing_address_1(),
            'billing_address_2'           => $order->get_billing_address_2(),
            'billing_city'                => $order->get_billing_city(),
            'billing_state'               => $order->get_billing_state(),
            'billing_postcode'            => $order->get_billing_postcode(),
            'billing_country'             => $order->get_billing_country(),
            'billing_email'               => $order->get_billing_email(),
            'billing_phone'               => $order->get_billing_phone(),
            'shipping_first_name'         => $order->get_shipping_first_name(),
            'shipping_last_name'          => $order->get_shipping_last_name(),
            'shipping_company'            => $order->get_shipping_company(),
            'shipping_address_1'          => $order->get_shipping_address_1(),
            'shipping_address_2'          => $order->get_shipping_address_2(),
            'shipping_city'               => $order->get_shipping_city(),
            'shipping_state'              => $order->get_shipping_state(),
            'shipping_postcode'           => $order->get_shipping_postcode(),
            'shipping_country'            => $order->get_shipping_country(),
            'payment_method'              => $order->get_payment_method(),
            'payment_method_title'        => $order->get_payment_method_title(),
            'status'                      => $order->get_status(),
            'checkout_order_received_url' => $order->get_checkout_order_received_url(),
            'customer_note'               => $order->get_customer_note()
        ];

        // if version is greater than 8.5.1
        if (version_compare(WC_VERSION, '8.5.1', '>=')) {
            $orderData = array_merge(
                $orderData,
                [
                    '_wc_order_attribution_referrer'           => $order->get_meta('_wc_order_attribution_referrer'),
                    '_wc_order_attribution_user_agent'         => $order->get_meta('_wc_order_attribution_user_agent'),
                    '_wc_order_attribution_utm_source'         => $order->get_meta('_wc_order_attribution_utm_source'),
                    '_wc_order_attribution_device_type'        => $order->get_meta('_wc_order_attribution_device_type'),
                    '_wc_order_attribution_source_type'        => $order->get_meta('_wc_order_attribution_source_type'),
                    '_wc_order_attribution_session_count'      => $order->get_meta('_wc_order_attribution_session_count'),
                    '_wc_order_attribution_session_entry'      => $order->get_meta('_wc_order_attribution_session_entry'),
                    '_wc_order_attribution_session_pages'      => $order->get_meta('_wc_order_attribution_session_pages'),
                    '_wc_order_attribution_session_start_time' => $order->get_meta('_wc_order_attribution_session_start_time'),
                ]
            );
        }

        // get line items information
        $lineItems = $this->getLineItems($order->get_items());

        if (!empty($lineItems)) {
            return array_merge($orderData, $lineItems);
        }

        return $orderData;
    }

    public function getProductData($product)
    {
        $productId = $product->get_id();
        $imageId = $product->get_image_id();
        $imageIds = $product->get_gallery_image_ids();
        $productGallery = [];

        if (\count($imageIds)) {
            foreach ($imageIds as $id) {
                $productGallery[] = wp_get_attachment_image_url($id, 'full');
            }
        }

        return [
            'product_id'             => $productId,
            'product_title'          => $product->get_name(),
            'product_content'        => $product->get_description(),
            'product_excerpt'        => $product->get_short_description(),
            'product_date'           => $product->get_date_created(),
            'product_date_gmt'       => $product->get_date_modified(),
            'product_status'         => $product->get_status(),
            'tags_input'             => $product->get_tag_ids(),
            'product_category'       => wc_get_product_category_list($productId),
            '_visibility'            => $product->get_catalog_visibility(),
            '_featured'              => $product->get_featured(),
            '_regular_price'         => $product->get_regular_price(),
            '_sale_price'            => $product->get_sale_price(),
            '_sale_price_dates_from' => $product->get_date_on_sale_from(),
            '_sale_price_dates_to'   => $product->get_date_on_sale_to(),
            '_sku'                   => $product->get_sku(),
            '_manage_stock'          => $product->get_manage_stock(),
            '_stock'                 => $product->get_stock_quantity(),
            '_backorders'            => $product->get_backorders(),
            '_low_stock_amount'      => 1,
            '_stock_status'          => $product->get_stock_status(),
            '_sold_individually'     => $product->get_sold_individually(),
            '_weight'                => $product->get_weight(),
            '_length'                => $product->get_length(),
            '_width'                 => $product->get_width(),
            '_height'                => $product->get_height(),
            '_purchase_note'         => $product->get_purchase_note(),
            'menu_order'             => $product->get_menu_order(),
            'comment_status'         => $product->get_reviews_allowed(),
            '_virtual'               => $product->get_virtual(),
            '_downloadable'          => $product->get_downloadable(),
            '_download_limit'        => $product->get_download_limit(),
            '_download_expiry'       => $product->get_download_expiry(),
            'product_type'           => $product->get_type(),
            '_product_url'           => get_permalink($productId),
            '_tax_status'            => $product->get_tax_status(),
            '_tax_class'             => $product->get_tax_class(),
            '_product_image'         => wp_get_attachment_image_url($imageId, 'full'),
            '_product_gallery'       => $productGallery,
        ];
    }

    public function getLineItems(array $orderItems)
    {
        $lineItems = [];

        if ($orderItems === []) {
            return $lineItems;
        }

        foreach ($orderItems as $item) {
            $productId = $item->get_product_id();
            $product = $item->get_product();

            $itemData = [
                'product_id'         => $productId,
                'variation_id'       => $item->get_variation_id(),
                'product_name'       => $item->get_name(),
                'quantity'           => $item->get_quantity(),
                'subtotal'           => $item->get_subtotal(),
                'total'              => $item->get_total(),
                'subtotal_tax'       => $item->get_subtotal_tax(),
                'tax_class'          => $item->get_tax_class(),
                'tax_status'         => $item->get_tax_status(),
                'product_sku'        => $product->get_sku(),
                'product_unit_price' => $product->get_price(),
            ];

            foreach ($this->getAcfFieldGroups(['product']) as $group) {
                foreach (acf_get_fields($group['ID']) as $field) {
                    $itemData[$field['_name']] = get_post_meta($productId, $field['_name'])[0];
                }
            }

            $lineItems['line_items'][] = (object) $itemData;
        }

        return $lineItems;
    }

    public function getAcfFieldGroups($type = [])
    {
        if (!self::isAcfInstalled()) {
            return [];
        }

        return array_filter(
            acf_get_field_groups(),
            fn ($group) => $group['active']
            && isset($group['location'][0][0]['value'])
            && \is_array($type) && \in_array($group['location'][0][0]['value'], $type)
        );
    }

    public function getAcfFieldData($acfFieldGroups, $postId)
    {
        if (!self::isAcfInstalled()) {
            return [];
        }

        $data = [];

        foreach ($acfFieldGroups as $group) {
            foreach (acf_get_fields($group['ID']) as $field) {
                $data[$field['_name']] = get_post_meta($postId, $field['_name'])[0];
            }
        }

        return $data;
    }

    public function getCustomCheckoutData($order)
    {
        if (!WooCommerceTrigger::isPluginInstalled()) {
            return [];
        }

        $data = [];
        $order = \is_object($order) ? (array) $order : $order;
        $checkoutFields = WC()->checkout()->get_checkout_fields();

        foreach ($checkoutFields as $group) {
            foreach ($group as $field) {
                if (!empty($field['custom'])) {
                    $data[$field['name']] = $order[$field['name']];
                }
            }
        }

        return $data;
    }

    public function getFlexibleCheckoutData($order)
    {
        if (!WooCommerceTrigger::isPluginInstalled()) {
            return [];
        }

        $data = [];
        $order = \is_object($order) ? (array) $order : $order;
        $checkoutFields = WC()->checkout()->get_checkout_fields();

        foreach ($checkoutFields as $groupKey => $group) {
            if ($groupKey == 'shipping') {
                continue;
            }

            foreach ($group as $fieldKey => $field) {
                if (empty($field['custom_field'])) {
                    continue;
                }

                $fieldKey = $field['name'] ?? $fieldKey;
                $data[$fieldKey] = $order[$fieldKey] ?? '';
            }
        }

        return $data;
    }

    public static function isPluginInstalled()
    {
        return class_exists('WooCommerce');
    }

    private static function isAcfInstalled()
    {
        return class_exists('ACF');
    }
}
