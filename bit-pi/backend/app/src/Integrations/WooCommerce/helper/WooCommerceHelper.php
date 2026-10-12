<?php

namespace BitApps\Pi\src\Integrations\WooCommerce\helper;

use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\Helpers\Utility;
use WC_Coupon;

if (!defined('ABSPATH')) {
    exit;
}

final class WooCommerceHelper
{
    public static function isPluginInstalled()
    {
        return class_exists('WooCommerce');
    }

    public static function getOrderData($order)
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
        $lineItems = self::getLineItems($order->get_items());

        if (!empty($lineItems)) {
            return array_merge($orderData, $lineItems);
        }

        return $orderData;
    }

    public static function getProductData($product)
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

    public static function getLineItems(array $orderItems)
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
                'product_sku'        => $product ? $product->get_sku() : '',
                'product_unit_price' => $product ? $product->get_price() : 0,
            ];

            foreach (self::getAcfFieldGroups(['product']) as $group) {
                foreach (acf_get_fields($group['ID']) as $field) {
                    $itemData[$field['_name']] = get_post_meta($productId, $field['_name'], true);
                }
            }

            $lineItems['line_items'][] = (object) $itemData;
        }

        return $lineItems;
    }

    public static function getAcfFieldGroups($type = [])
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

    public static function getAcfFieldData($acfFieldGroups, $postId)
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

    public static function getCustomCheckoutData($order)
    {
        if (!self::isPluginInstalled()) {
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

    public static function getFlexibleCheckoutData($order)
    {
        if (!self::isPluginInstalled()) {
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

    public static function getReviewData($comment)
    {
        if (empty($comment) || $comment->comment_type !== 'review' || get_post_type($comment->comment_post_ID) !== 'product') {
            return [];
        }

        $product = wc_get_product($comment->comment_post_ID);

        return [
            'comment_id'   => $comment->comment_ID,
            'comment_data' => Utility::extractObjectAttributes($comment),
            'rating'       => (int) get_comment_meta($comment->comment_ID, 'rating', true),
            'product_id'   => $comment->comment_post_ID,
            'product_name' => $product ? $product->get_name() : '',
            'product_data' => $product ? $product->get_data() : [],
        ];
    }

    public static function formatDate($date)
    {
        return \is_callable([$date, 'format']) ? $date->format('Y-m-d H:i:s') : $date;
    }

    public static function getProductCategory()
    {
        $allCategory = array_map(
            function ($category) {
                return [
                    'label' => $category->name,
                    'value' => $category->slug
                ];
            },
            self::getTerms('product_cat')
        );

        return Response::success($allCategory);
    }

    public static function getProducts()
    {
        $products = wc_get_products(
            [
                'limit'  => -1,
                'return' => 'objects',
            ]
        );

        $allProduct = array_map(
            function ($product) {
                return [
                    'label' => self::getProductOptionLabel($product),
                    'value' => $product->get_id()
                ];
            },
            $products
        );

        return Response::success($allProduct);
    }

    public static function getProductVariations(Request $request)
    {
        $product = wc_get_product((int) $request->product_id);

        if (empty($product) || !$product->is_type('variable')) {
            return Response::success([]);
        }

        $variations = [];

        foreach ($product->get_children() as $variationId) {
            $variation = wc_get_product($variationId);

            if (empty($variation)) {
                continue;
            }

            $variations[] = [
                'label' => \sprintf('%1$s (#%2$d)', wc_get_formatted_variation($variation, true, true) ?: $variation->get_name(), $variationId),
                'value' => $variationId,
            ];
        }

        return Response::success($variations);
    }

    public static function getShippingClass()
    {
        $items = array_map(
            function ($shipping) {
                return [
                    'label' => $shipping->name,
                    'value' => $shipping->term_id
                ];
            },
            self::getTerms('product_shipping_class')
        );

        return Response::success($items);
    }

    public static function getProductTags()
    {
        $tags = array_map(
            function ($tag) {
                return [
                    'label' => $tag->name,
                    'value' => $tag->term_id
                ];
            },
            self::getTerms('product_tag')
        );

        return Response::success($tags);
    }

    public static function getProductBrands()
    {
        $brands = array_map(
            function ($brand) {
                return [
                    'label' => $brand->name,
                    'value' => $brand->term_id
                ];
            },
            self::getTerms('product_brand')
        );

        return Response::success($brands);
    }

    public static function getProductCategories()
    {
        $categories = array_map(
            function ($category) {
                return [
                    'label' => $category->name,
                    'value' => $category->term_id
                ];
            },
            self::getTerms('product_cat')
        );

        return Response::success($categories);
    }

    public static function getOrderStatus()
    {
        $status = [];

        if (!\function_exists('wc_get_order_statuses')) {
            return Response::success($status);
        }

        foreach (wc_get_order_statuses() as $key => $label) {
            $status[] = [
                'label' => $label,
                'value' => $key
            ];
        }

        return Response::success($status);
    }

    public static function getPaymentMethods()
    {
        if (!\function_exists('WC')) {
            return Response::success([]);
        }

        $gateways = array_map(
            function ($gateway) {
                return [
                    'label' => $gateway->title,
                    'value' => $gateway->id
                ];
            },
            WC()->payment_gateways()->get_available_payment_gateways()
        );

        return Response::success(array_values($gateways));
    }

    public static function getCoupons()
    {
        $posts = get_posts(
            [
                'post_type'      => 'shop_coupon',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            ]
        );

        $coupons = array_map(
            function ($post) {
                $coupon = new WC_Coupon($post->ID);
                $code = $coupon->get_code();

                return ['label' => $code, 'value' => $code];
            },
            $posts
        );

        return Response::success($coupons);
    }

    public static function getReviews()
    {
        return Response::success(self::getReviewsByStatus('all'));
    }

    public static function getPendingReviews()
    {
        return Response::success(self::getReviewsByStatus('hold'));
    }

    public static function getAttributes()
    {
        $attributes = array_map(
            function ($attribute) {
                return [
                    'label' => $attribute->attribute_label,
                    'value' => $attribute->attribute_id
                ];
            },
            wc_get_attribute_taxonomies()
        );

        return Response::success(array_values($attributes));
    }

    private static function isAcfInstalled()
    {
        return class_exists('ACF');
    }

    private static function getReviewsByStatus($status)
    {
        $reviews = get_comments(
            [
                'post_type' => 'product',
                'status'    => $status,
                'meta_key'  => 'rating',
            ]
        );

        return array_map(
            function ($review) {
                $rating = (int) get_comment_meta($review->comment_ID, 'rating', true);

                $label = \sprintf(
                    // translators: %1$d: review ID, %2$s: reviewer name, %3$s: product name, %4$d: rating
                    __('#%1$d by %2$s on %3$s (%4$d stars)', 'bit-pi'),
                    $review->comment_ID,
                    $review->comment_author,
                    get_the_title($review->comment_post_ID),
                    $rating
                );

                if ($review->comment_approved === '0') {
                    $label .= ' - ' . __('Pending', 'bit-pi');
                }

                return [
                    'label' => $label,
                    'value' => (string) $review->comment_ID,
                ];
            },
            $reviews
        );
    }

    private static function getProductOptionLabel($product)
    {
        $statuses = get_post_statuses();
        $status = $product->get_status();

        if ($status === 'publish' || !isset($statuses[$status])) {
            return \sprintf('%1$s (#%2$d)', $product->get_name(), $product->get_id());
        }

        return \sprintf('%1$s (#%2$d, %3$s)', $product->get_name(), $product->get_id(), $statuses[$status]);
    }

    private static function getTerms($taxonomy)
    {
        $terms = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'orderby'    => 'term_id',
                'hide_empty' => false
            ]
        );

        return is_wp_error($terms) ? [] : $terms;
    }
}
