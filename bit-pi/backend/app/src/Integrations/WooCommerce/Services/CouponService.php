<?php

namespace BitApps\Pi\src\Integrations\WooCommerce\Services;

use BitApps\Pi\src\Integrations\IntegrationHelper;
use WC_Coupon;
use WP_Term;

if (!defined('ABSPATH')) {
    exit;
}

final class CouponService extends BaseService
{
    public function createCoupon()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'discount_type' => ['required'],
            'coupon_code'   => ['required'],
            'coupon_amount' => ['required'],
            'expiry_date'   => ['required']
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $couponId = wp_insert_post(
            [
                'post_title'   => $fieldMappings['coupon_code'],
                'post_content' => $fieldMappings['coupon_description'] ?? '',
                'post_excerpt' => $fieldMappings['coupon_description'] ?? '',
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id() === 0 ? 1 : get_current_user_id(),
                'post_type'    => 'shop_coupon',
            ]
        );

        if (is_wp_error($couponId)) {
            return [
                'response'    => $couponId->get_error_message(),
                'payload'     => $fieldMappings,
                'status_code' => $couponId->get_error_code(),
            ];
        }

        $fieldMappings = $this->formatCouponBooleanFields($fieldMappings);
        $fieldMappings['customer_email'] = $this->parseEmails($fieldMappings['customer_email'] ?? '');
        $fieldMappings = $this->formatCouponCategoryFields($fieldMappings);

        $metaData = array_filter(
            $fieldMappings,
            function ($key) {
                return !\in_array($key, ['coupon_code', 'coupon_description']);
            },
            ARRAY_FILTER_USE_KEY
        );

        foreach ($metaData as $key => $value) {
            update_post_meta($couponId, $key, $value);
        }

        return [
            'response'    => $this->getCouponData($couponId),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function updateCoupon()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'coupon_code' => ['required'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $coupon = new WC_Coupon($fieldMappings['coupon_code']);

        if (empty($coupon->get_id())) {
            return [
                'response'    => __('Coupon not found with the given code.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $fieldMappings = $this->formatCouponBooleanFields($fieldMappings);

        if (isset($fieldMappings['customer_email'])) {
            $fieldMappings['customer_email'] = $this->parseEmails($fieldMappings['customer_email'] ?? '');
        }

        $productCategory = $this->nodeInfoProvider->getFieldMapConfigs('product-category.value');

        if (!isset($fieldMappings['product_categories']) && !empty($productCategory)) {
            $fieldMappings['product_categories'] = $productCategory;
        }

        $fieldMappings = $this->formatCouponCategoryFields($fieldMappings);

        $keyFunctions = [
            'coupon_amount'              => 'set_amount',
            'discount_type'              => 'set_discount_type',
            'coupon_description'         => 'set_description',
            'expiry_date'                => 'set_date_expires',
            'usage_limit'                => 'set_usage_limit',
            'limit_usage_to_x_items'     => 'set_usage_limit_per_item',
            'usage_count'                => 'set_usage_count',
            'individual_use'             => 'set_individual_use',
            'product_ids'                => 'set_product_ids',
            'exclude_product_ids'        => 'set_excluded_product_ids',
            'product_categories'         => 'set_product_categories',
            'exclude_product_categories' => 'set_excluded_product_categories',
            'customer_email'             => 'set_email_restrictions',
            'free_shipping'              => 'set_free_shipping',
            'minimum_amount'             => 'set_minimum_amount',
            'maximum_amount'             => 'set_maximum_amount',
            'exclude_sale_items'         => 'set_exclude_sale_items',
        ];

        foreach ($keyFunctions as $key => $function) {
            if (isset($fieldMappings[$key])) {
                $coupon->{$function}($fieldMappings[$key]);
            }
        }

        if (!$coupon->save()) {
            return [
                'response'    => __('Failed to update coupon.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 500,
            ];
        }

        return [
            'response'    => $coupon->get_data(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function updateCouponCode()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'coupon_id'   => ['required'],
            'coupon_code' => ['required'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $coupon = new WC_Coupon($fieldMappings['coupon_id']);

        if (empty($coupon->get_id())) {
            return [
                'response'    => __('Coupon not found with the given id.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $coupon->set_code($fieldMappings['coupon_code']);

        if (!$coupon->save()) {
            return [
                'response'    => __('Failed to update coupon.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 500,
            ];
        }

        return [
            'response'    => $coupon->get_data(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function addEmailsToCoupon()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'coupon_code_or_id' => ['required'],
            'customer_email'    => ['required'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $coupon = new WC_Coupon($fieldMappings['coupon_code_or_id']);

        if (empty($coupon->get_id())) {
            return [
                'response'    => __('Coupon not found with the given code or id.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $customerEmails = $this->parseEmails($fieldMappings['customer_email']);

        if ($fieldMappings['append_emails'] ?? false) {
            $existingEmails = $coupon->get_email_restrictions();

            $customerEmails = array_unique(array_merge($existingEmails, $customerEmails));
        }

        $coupon->set_email_restrictions($customerEmails);

        if (!$coupon->save()) {
            return [
                'response'    => __('Failed to update coupon.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 500,
            ];
        }

        return [
            'response'    => $coupon->get_data(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function applyCouponToCart()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'coupon_code_or_id' => ['required'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $coupon = new WC_Coupon($fieldMappings['coupon_code_or_id']);

        if (empty($coupon->get_id())) {
            return [
                'response'    => __('Coupon not found with the given code or id.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $status = WC()->cart->apply_coupon($coupon->get_code());

        if (!$status) {
            return [
                'response'    => __('Failed to apply coupon.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 500,
            ];
        }

        return [
            'response'    => WC()->cart->get_cart(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function getAppliedCoupons()
    {
        return [
            'response'    => WC()->cart->get_applied_coupons(),
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    public function removeCouponFromCart()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'coupon_code_or_id' => ['required'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $coupon = new WC_Coupon($fieldMappings['coupon_code_or_id']);

        if (empty($coupon->get_id())) {
            return [
                'response'    => __('Coupon not found with the given code or id.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $status = WC()->cart->remove_coupon($coupon->get_code());

        if (!$status) {
            return [
                'response'    => __('Failed to remove coupon.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 500,
            ];
        }

        return [
            'response'    => WC()->cart->get_cart(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function deleteCoupon()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'coupon_code_or_id' => ['required'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $coupon = new WC_Coupon($fieldMappings['coupon_code_or_id']);

        if (empty($coupon->get_id())) {
            return [
                'response'    => __('Coupon not found with the given code or id.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        if ($fieldMappings['force_delete'] ?? false) {
            $status = wp_delete_post($coupon->get_id(), true);
        } else {
            $status = $coupon->delete(false);
        }

        if (!$status) {
            return [
                'response'    => __('Failed to delete coupon.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 500,
            ];
        }

        return [
            'response'    => __('Coupon deleted successfully.', 'bit-pi'),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function getAllCoupon()
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
                return $this->getCouponData($post->ID);
            },
            $posts
        );

        return [
            'response'    => $coupons,
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    public function getCouponById()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'id' => ['required', 'integer']
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        return [
            'response'    => $this->getCouponData($fieldMappings['id']),
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    public function getCouponTotals()
    {
        $posts = get_posts(
            [
                'post_type'      => 'shop_coupon',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            ]
        );

        $data = [
            'percent' => [
                'discount_type' => 'Percentage discount',
                'total_coupons' => 0,
                'coupons'       => []
            ],
            'fixed_cart' => [
                'discount_type' => 'Fixed cart discount',
                'total_coupons' => 0,
                'coupons'       => []
            ],
            'fixed_product' => [
                'discount_type' => 'Fixed product discount',
                'total_coupons' => 0,
                'coupons'       => []
            ],
        ];

        foreach ($posts as $post) {
            $coupon = new WC_Coupon($post->ID);
            $type = $coupon->get_discount_type();

            if (isset($data[$type])) {
                ++$data[$type]['total_coupons'];
                $data[$type]['coupons'][] = $coupon->get_data();
            }
        }

        foreach ($data as $type => $group) {
            // Flows mapped before total_coupons held the count read it from this key.
            $data[$type]['totals'] = $group['total_coupons'];
        }

        return [
            'response'    => $data,
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    private function getCouponData($id)
    {
        $coupon = new WC_Coupon($id);

        return $coupon->get_data();
    }

    private function formatCouponCategoryFields($fieldMappings)
    {
        foreach (['product_categories', 'exclude_product_categories'] as $key) {
            if (isset($fieldMappings[$key])) {
                $fieldMappings[$key] = $this->getProductCategoryIds($fieldMappings[$key]);
            }
        }

        return $fieldMappings;
    }

    private function getProductCategoryIds($categories)
    {
        $categoryIds = [];

        foreach (\is_array($categories) ? $categories : explode(',', (string) $categories) as $category) {
            $category = trim((string) $category);
            $term = is_numeric($category) ? get_term((int) $category, 'product_cat') : null;

            if (!$term instanceof WP_Term && $category !== '') {
                $term = get_term_by('slug', $category, 'product_cat');
            }

            if ($term instanceof WP_Term) {
                $categoryIds[] = $term->term_id;
            }
        }

        return array_values(array_unique($categoryIds));
    }

    private function formatCouponBooleanFields($fieldMappings)
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
}
