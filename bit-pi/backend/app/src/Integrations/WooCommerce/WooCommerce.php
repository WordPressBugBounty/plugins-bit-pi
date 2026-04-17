<?php

namespace BitApps\Pi\src\Integrations\WooCommerce;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use WC_Coupon;

class WooCommerce
{
    public function getPostTypes()
    {
        $allPostTypes = array_map(
            function ($postType) {
                return [
                    'label' => $postType->label,
                    'value' => $postType->name
                ];
            },
            get_post_types(['public' => true], 'objects')
        );

        return Response::success($allPostTypes);
    }

    public function getPosts(Request $request)
    {
        $validated = $request->validate(
            [
                'postType' => ['nullable', 'array'],
            ]
        );

        $posts = get_posts(
            [
                'post_type'      => \is_array($validated['postType']) && \count($validated['postType']) ? $validated['postType'] : get_post_types(['public' => true]),
                'posts_per_page' => -1,
                'post_status'    => 'any',
            ]
        );

        $allPosts = array_map(
            function ($post) {
                return [
                    'label' => $post->post_title,
                    'value' => $post->ID
                ];
            },
            $posts
        );

        return Response::success($allPosts);
    }

    public function getProductCategory()
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

    public function getProducts()
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
                    'label' => $product->get_name(),
                    'value' => $product->get_id()
                ];
            },
            $products
        );

        return Response::success($allProduct);
    }

    public function getShippingClass()
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

    public function getProductTags()
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

    public function getProductBrands()
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

    public function getProductCategories()
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

    public function getOrderStatus()
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

    public function getPaymentMethods()
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

    public function getCoupons()
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

    private static function getTerms($taxonomy)
    {
        return get_terms(
            [
                'taxonomy'   => $taxonomy,
                'orderby'    => 'term_id',
                'hide_empty' => false
            ]
        );
    }
}
