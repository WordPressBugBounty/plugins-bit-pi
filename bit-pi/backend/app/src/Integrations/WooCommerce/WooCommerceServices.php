<?php

namespace BitApps\Pi\src\Integrations\WooCommerce;

use Automattic\WooCommerce\Admin\API\Reports\Products\Query;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use WC_Coupon;
use WC_Customer;
use WC_Product_Attribute;
use WC_Product_Variation;

if (!defined('ABSPATH')) {
    exit;
}


final class WooCommerceServices
{
    private $nodeInfoProvider;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    /**
     * Create Order.
     *
     * @return collection
     */
    public function createOrder()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $fieldMappings['billingAddress'] = $this->nodeInfoProvider->getFieldMapRepeaters('billing-field-map.value', false, true, 'billing_field', 'value');
        $fieldMappings['shippingAddress'] = $this->nodeInfoProvider->getFieldMapRepeaters('shipping-field-map.value', false, true, 'shipping_field', 'value');

        $mapProductIds = $this->nodeInfoProvider->getFieldMapConfigs('map-product-ids.value') ?? false;
        $shippingSameAsBilling = $this->nodeInfoProvider->getFieldMapConfigs('shipping-same-as-billing.value') ?? false;

        $rules = [
            'status'                    => ['required', 'sanitize:text'],
            'payment_method'            => ['required', 'sanitize:text'],
            'payment_method_title'      => ['required', 'sanitize:text'],
            'billingAddress.first_name' => ['required', 'sanitize:text'],
            'billingAddress.last_name'  => ['required', 'sanitize:text'],
            'billingAddress.email'      => ['required', 'sanitize:email'],
            'billingAddress.phone'      => ['required', 'sanitize:text'],
            'billingAddress.address_1'  => ['required', 'sanitize:text'],
            'billingAddress.city'       => ['required', 'sanitize:text'],
            'billingAddress.state'      => ['required', 'sanitize:text'],
            'billingAddress.country'    => ['required', 'sanitize:text'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        if (empty($fieldMappings['product_ids']) && empty($fieldMappings['custom_product_ids'])) {
            return [
                'response'    => __('At least one product is required to create an order.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 422,
            ];
        }

        $order = wc_create_order();

        if (is_wp_error($order)) {
            return [
                'response'    => $order->get_error_messages(),
                'payload'     => $fieldMappings,
                'status_code' => $order->get_error_code(),
            ];
        }

        $quantity = $fieldMappings['quantity'] ?? 1;
        $productIds = $fieldMappings['product_ids'];
        $billingAddress = $fieldMappings['billingAddress'];

        if ($mapProductIds) {
            $productIds = array_map('trim', explode(',', $fieldMappings['custom_product_ids']));
        }

        foreach ($productIds as $productId) {
            $product = wc_get_product($productId);

            if (empty($product)) {
                continue;
            }

            $variationData = [];

            if ($product->is_type('variation') && method_exists($product, 'get_variation_attributes')) {
                $variationData = $product->get_variation_attributes();
            }

            $order->add_product($product, $quantity, $variationData);
        }

        $user = get_user_by('email', $billingAddress['email']);

        if (isset($user->ID)) {
            $order->set_customer_id($user->ID);
        }

        $shippingAddress = $shippingSameAsBilling ? $billingAddress : $fieldMappings['shippingAddress'];

        $order->set_address($billingAddress, 'billing');
        $order->set_address($shippingAddress, 'shipping');

        $order->set_status($fieldMappings['status']);
        $order->set_payment_method($fieldMappings['payment_method']);
        $order->set_payment_method_title($fieldMappings['payment_method_title']);
        $order->add_order_note($fieldMappings['order_note'] ?? '');

        if (method_exists($order, 'apply_coupon') && isset($fieldMappings['coupon_code'])) {
            $order->apply_coupon($fieldMappings['coupon_code']);
        }

        $order->calculate_totals();
        $order->save();

        return [
            'response'    => $order->get_data(),
            'payload'     => $fieldMappings,
            'status_code' => 200
        ];
    }

    /**
     * Update Order Status.
     */
    public function updateOrderStatus(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'order_id' => ['required', 'integer'],
            'status'   => ['required', 'sanitize:text'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $order = wc_get_order($fieldMappings['order_id']);

        if (empty($order)) {
            return [
                'response'    => __('Order not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $order->update_status($fieldMappings['status']);

        return [
            'response'    => $order->get_data(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Add or Update Order Meta (Custom Fields).
     */
    public function addOrUpdateOrderMeta(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();
        $fieldMappings['custom_fields_mapping'] = $this->nodeInfoProvider->getFieldMapRepeaters('custom-field-mapping.value', false, true, 'meta_key', 'value');

        $rules = [
            'order_id' => ['required', 'integer'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $order = wc_get_order($fieldMappings['order_id']);

        if (empty($order)) {
            return [
                'response'    => __('Order not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        if (empty($fieldMappings['custom_fields_mapping'])) {
            return [
                'response'    => __('At least one custom field is required to add or update order meta.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 422,
            ];
        }

        foreach ($fieldMappings['custom_fields_mapping'] as $key => $value) {
            $order->update_meta_data($key, $value);
        }

        $order->save();

        return [
            'response'    => $order->get_data(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Get total orders count.
     */
    public function getTotalOrdersCount(): array
    {
        $orders = [];

        foreach (wc_get_order_statuses() as $key => $label) {
            $orders[] = [
                'slug'  => $key,
                'label' => $label,
                'count' => wc_orders_count($key),
            ];
        }

        return [
            'response'    => $orders,
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    /**
     * Get refunded orders.
     */
    public function getRefundedOrders(): array
    {
        $orders = wc_get_orders(
            [
                'type'   => 'shop_order_refund',
                'limit'  => -1,
                'return' => 'objects',
            ]
        );

        $refundedOrders = [];

        foreach ($orders as $order) {
            if (!$order || !is_a($order, 'WC_Order_Refund')) {
                continue;
            }

            $refundedOrders[] = $order->get_data();
        }

        return [
            'response'    => $refundedOrders,
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    /**
     * Get all orders.
     */
    public function getAllOrders(): array
    {
        return [
            'response'    => WooCommerceActionHelper::getOrders(),
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    /**
     * Get all orders by status.
     */
    public function getOrderByStatus(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'status' => ['required', 'sanitize:text'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        return [
            'response'    => WooCommerceActionHelper::getOrders($fieldMappings['status']),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Get all orders by billing email.
     */
    public function getOrderByBillingEmail(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'billing_email' => ['required', 'sanitize:text'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        return [
            'response'    => WooCommerceActionHelper::getOrders(null, null, $fieldMappings['billing_email']),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Get all orders by customer ID.
     */
    public function getOrderByCustomerId(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'customer_id' => ['required', 'integer'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        return [
            'response'    => WooCommerceActionHelper::getOrders(null, $fieldMappings['customer_id']),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Get all orders by id.
     */
    public function getOrderById(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'id' => ['required', 'integer'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $order = wc_get_order($fieldMappings['id']);

        if (empty($order)) {
            return [
                'response'    => __('Order not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        return [
            'response'    => $order->get_data(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Get customer total spent.
     */
    public function getCustomerTotalSpent(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'customer_id' => ['required', 'integer'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $customer = new WC_Customer($fieldMappings['customer_id']);

        if (empty($customer->get_id())) {
            return [
                'response'    => __('Customer not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $customerData = $customer->get_data();
        $lastOrder = $customer->get_last_order();

        $customerData['total_spent'] = $customer->get_total_spent();
        $customerData['total_spent_round'] = round($customerData['total_spent']);
        $customerData['total_orders'] = $customer->get_order_count();
        $customerData['last_order'] = $lastOrder ? $lastOrder->get_data() : null;

        return [
            'response'    => $customerData,
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Get customer last order.
     */
    public function getCustomerLastOrder(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'customer_id' => ['required', 'integer'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $customer = new WC_Customer($fieldMappings['customer_id']);

        if (empty($customer->get_id())) {
            return [
                'response'    => __('Customer not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $lastOrder = $customer->get_last_order();

        if (empty($lastOrder)) {
            return [
                'response'    => __('No last order found for the customer.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        return [
            'response'    => $lastOrder->get_data(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Add order note.
     *
     * @return collection
     */
    public function addOrderNote()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'order_id' => ['required', 'integer'],
            'note'     => ['required', 'sanitize:text'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $order = wc_get_order($fieldMappings['order_id']);

        if (empty($order)) {
            return [
                'response'    => __('Order not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $status = $order->add_order_note($fieldMappings['note']);

        if (!$status) {
            return [
                'response'    => __('Failed to add order note.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 500,
            ];
        }

        return [
            'response'    => __('Order note added successfully.', 'bit-pi'),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Get all customers.
     *
     * @return collection
     */
    public function getAllCustomers()
    {
        $customers = get_users(['role' => 'customer']);

        $customers = array_map(
            function ($customer) {
                $wcCustomer = new WC_Customer($customer->ID);

                return $wcCustomer->get_data();
            },
            $customers
        );

        return [
            'response'    => $customers,
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    /**
     * Get all customers.
     *
     * @return collection
     */
    public function getCustomerById()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'id' => ['required', 'integer'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $customer = new WC_Customer($fieldMappings['id']);

        if (empty($customer->get_id())) {
            return [
                'response'    => __('Customer not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        return [
            'response'    => $customer->get_data(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Get all customers.
     *
     * @return collection
     */
    public function getCustomerByEmail()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'email' => ['required', 'sanitize:email'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $user = get_user_by('email', $fieldMappings['email']);

        if (empty($user->ID)) {
            return [
                'response'    => __('Customer not found with the given email.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $customer = new WC_Customer($user->ID);

        if (empty($customer->get_id())) {
            return [
                'response'    => __('Customer not found with the given email.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        return [
            'response'    => $customer->get_data(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Create new customer.
     *
     * @return collection
     */
    public function createNewCustomer()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $fieldMappings['billing_address'] = $this->nodeInfoProvider->getFieldMapRepeaters('billing-field-map.value', false, true, 'billing_field', 'value');
        $fieldMappings['shipping_address'] = $this->nodeInfoProvider->getFieldMapRepeaters('shipping-field-map.value', false, true, 'shipping_field', 'value');

        $generatePassword = $this->nodeInfoProvider->getFieldMapConfigs('generate-password.value') ?? false;
        $shippingSameAsBilling = $this->nodeInfoProvider->getFieldMapConfigs('shipping-same-as-billing.value') ?? false;

        $rules = [
            'email' => ['required', 'sanitize:email'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $username = isset($fieldMappings['username'])
            ? sanitize_user($fieldMappings['username'])
            : sanitize_user(current(explode('@', $fieldMappings['email'])));

        $password = $generatePassword
            ? wp_generate_password()
            : ($fieldMappings['password'] ?? '');

        $shippingAddress = $shippingSameAsBilling
            ? $fieldMappings['billing_address']
            : $fieldMappings['shipping_address'];

        $billingEmail = $fieldMappings['billing_address']['email'] ?? $fieldMappings['email'];

        $userId = wc_create_new_customer($fieldMappings['email'], $username, $password);

        if (is_wp_error($userId)) {
            return [
                'response'    => $userId->get_error_messages(),
                'payload'     => $fieldMappings,
                'status_code' => $userId->get_error_code(),
            ];
        }

        $customer = new WC_Customer($userId);

        if (empty($customer->get_id())) {
            return [
                'response'    => __('Failed to create customer.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 500,
            ];
        }

        $customer->set_first_name($fieldMappings['first_name'] ?? '');
        $customer->set_last_name($fieldMappings['last_name'] ?? '');
        $customer->set_display_name($fieldMappings['display_name'] ?? '');
        $customer->set_billing_email($billingEmail);

        $addressFieldKeys = [
            'first_name', 'last_name', 'phone', 'company', 'address_1',
            'address_2', 'city', 'state', 'postcode', 'country',
        ];

        foreach ($addressFieldKeys as $key) {
            if (isset($fieldMappings['billing_address'][$key])) {
                $customer->{"set_billing_{$key}"}($fieldMappings['billing_address'][$key]);
            }

            if (isset($shippingAddress[$key])) {
                $customer->{"set_shipping_{$key}"}($shippingAddress[$key]);
            }
        }

        $customer->save();

        return [
            'response'    => $customer->get_data(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Create simple product.
     *
     * @return collection
     */
    public function createSimpleProduct()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'name'          => ['required', 'sanitize:text'],
            'regular_price' => ['required', 'sanitize:text'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $postId = wp_insert_post(
            [
                'post_title'     => $fieldMappings['name'],
                'post_content'   => $fieldMappings['description'] ?? '',
                'post_excerpt'   => $fieldMappings['short_description'] ?? '',
                'post_status'    => $fieldMappings['status'] ?? 'draft',
                'post_name'      => $fieldMappings['slug'] ?? '',
                'post_type'      => 'product',
                'comment_status' => $fieldMappings['reviews_allowed'] ?? false,
            ]
        );

        if (is_wp_error($postId) || !$postId) {
            return [
                'response'    => is_wp_error($postId) ? $postId->get_error_messages() : __('Failed to create product.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => is_wp_error($postId) ? $postId->get_error_code() : 500,
            ];
        }

        $result = wp_set_object_terms($postId, 'simple', 'product_type');

        if (is_wp_error($result)) {
            return [
                'response'    => $result->get_error_message(),
                'payload'     => $fieldMappings,
                'status_code' => $result->get_error_code(),
            ];
        }

        $propsKeys = [
            'price',
            'regular_price',
            'sale_price',
            'tax_status',
            'tax_class',
            'virtual',
            'shipping_class_id',
            'upsell_ids',
            'cross_sell_ids',
            'purchase_note',
            'menu_order',
            'reviews_allowed',
        ];

        $product = wc_get_product($postId);
        $product = WooCommerceActionHelper::updateProductProperties($fieldMappings, $product, $propsKeys);

        WooCommerceActionHelper::updateProductMetadata($fieldMappings, $postId);
        WooCommerceActionHelper::setProductTerms($fieldMappings, $postId);

        if (isset($fieldMappings['featured_image'])) {
            $attachmentId = WooCommerceActionHelper::uploadProductImage(
                $fieldMappings['featured_image'],
                $postId,
                $fieldMappings
            );

            if (\is_array($attachmentId)) {
                return $attachmentId;
            }

            set_post_thumbnail($postId, $attachmentId);
        }

        if (isset($fieldMappings['gallery_images'])) {
            $imageUrls = array_map('trim', explode(',', $fieldMappings['gallery_images']));

            $attachmentIds = [];
            foreach ($imageUrls as $imageUrl) {
                $attachmentId = WooCommerceActionHelper::uploadProductImage(
                    $imageUrl,
                    $postId,
                    $fieldMappings
                );

                if (\is_array($attachmentId)) {
                    return $attachmentId;
                }

                $attachmentIds[] = $attachmentId;
            }

            update_post_meta($postId, '_product_image_gallery', implode(',', $attachmentIds));
        }

        // Ensure price lookup table is updated (for WooCommerce 3.7+)
        wc_delete_product_transients($postId);
        wc_update_product_lookup_tables($product->get_id());

        return [
            'response'    => $product->get_data(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Create product variation.
     *
     * @return collection
     */
    public function createProductVariation()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();
        $fieldMappings['attributes'] = $this->nodeInfoProvider->getFieldMapRepeaters('attribute-field-mapping.value', false, true, 'attribute_key', 'value');

        $rules = [
            'product_id'    => ['required', 'sanitize:text'],
            'regular_price' => ['required', 'sanitize:text'],
            'sku'           => ['required', 'sanitize:text'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $variation = new WC_Product_Variation();
        $variation->set_parent_id((int) $fieldMappings['product_id']);
        $variation->set_attributes($fieldMappings['attributes']);

        if (isset($fieldMappings['variation_image'])) {
            $attachmentId = WooCommerceActionHelper::getVariationAttachmentId(
                $fieldMappings['variation_image'],
                $variation->get_id()
            );

            $variation->set_image_id($attachmentId);
        }

        $propsKeys = [
            'sku',
            'regular_price',
            'sale_price',
            'stock_status',
            'date_on_sale_from',
            'date_on_sale_to',
            'shipping_class_id',
            'description',
        ];

        $variation = WooCommerceActionHelper::updateProductProperties($fieldMappings, $variation, $propsKeys);

        return [
            'response'    => $variation->get_data(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Update existing product.
     *
     * @return collection
     */
    public function updateExistingProduct()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'product_id' => ['required', 'integer'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $productId = $fieldMappings['product_id'];
        $product = wc_get_product($productId);

        if (empty($product)) {
            return [
                'response'    => __('Product not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $propsKeys = [
            'name',
            'description',
            'short_description',
            'status',
            'slug',
            'price',
            'regular_price',
            'sale_price',
            'tax_status',
            'tax_class',
            'virtual',
            'shipping_class_id',
            'upsell_ids',
            'cross_sell_ids',
            'purchase_note',
            'menu_order',
            'reviews_allowed',
        ];

        $product = WooCommerceActionHelper::updateProductProperties($fieldMappings, $product, $propsKeys);

        WooCommerceActionHelper::updateProductMetadata($fieldMappings, $productId);
        WooCommerceActionHelper::setProductTerms($fieldMappings, $productId);

        if (isset($fieldMappings['featured_image'])) {
            $attachmentId = WooCommerceActionHelper::uploadProductImage(
                $fieldMappings['featured_image'],
                $productId,
                $fieldMappings
            );

            if (\is_array($attachmentId)) {
                return $attachmentId;
            }

            set_post_thumbnail($productId, $attachmentId);
        }

        if (isset($fieldMappings['gallery_images'])) {
            $imageUrls = array_map('trim', explode(',', $fieldMappings['gallery_images']));

            $attachmentIds = [];
            foreach ($imageUrls as $imageUrl) {
                $attachmentId = WooCommerceActionHelper::uploadProductImage(
                    $imageUrl,
                    $productId,
                    $fieldMappings
                );

                if (\is_array($attachmentId)) {
                    return $attachmentId;
                }

                $attachmentIds[] = $attachmentId;
            }

            update_post_meta($productId, '_product_image_gallery', implode(',', $attachmentIds));
        }

        return [
            'response'    => $product->get_data(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Get all products.
     *
     * @param null|array|string $type
     * @param null|array|string $category
     */
    public function getProducts($type = null, $category = null): array
    {
        $products = WooCommerceActionHelper::getProducts($type, $category);

        return [
            'response'    => $products,
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    /**
     * Get products by category.
     */
    public function getAllProductsByCategory(): array
    {
        $categorySlug = $this->nodeInfoProvider->getFieldMapConfigs('product-category.value') ?? null;
        $payload = ['category' => $categorySlug];

        $rules = [
            'category' => ['required', 'sanitize:text']
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($payload, $rules);

        if ($validation) {
            return $validation;
        }

        $products = WooCommerceActionHelper::getProducts(null, $categorySlug);

        return [
            'response'    => $products,
            'payload'     => $payload,
            'status_code' => 200,
        ];
    }

    /**
     * Get product by id.
     */
    public function getProductById(): array
    {
        $productId = $this->nodeInfoProvider->getFieldMapData()['id'] ?? null;
        $payload = ['product_id' => $productId];

        $rules = [
            'product_id' => ['required', 'integer']
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($payload, $rules);

        if ($validation) {
            return $validation;
        }

        $product = WooCommerceActionHelper::getProductById($productId);

        return [
            'response'    => $product,
            'payload'     => $payload,
            'status_code' => 200,
        ];
    }

    /**
     * Get product by sku.
     */
    public function getProductBySku(): array
    {
        $productSku = $this->nodeInfoProvider->getFieldMapData()['sku'] ?? null;
        $payload = ['product_sku' => $productSku];

        $rules = [
            'product_sku' => ['required', 'sanitize:text']
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($payload, $rules);

        if ($validation) {
            return $validation;
        }

        $productId = wc_get_product_id_by_sku($productSku);

        if (empty($productId)) {
            return [
                'response'    => __('Product not found with the given SKU.', 'bit-pi'),
                'payload'     => $payload,
                'status_code' => 404,
            ];
        }

        $product = WooCommerceActionHelper::getProductById($productId);

        return [
            'response'    => $product,
            'payload'     => $payload,
            'status_code' => 200,
        ];
    }

    /**
     * Update product stock.
     */
    public function updateProductStock(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'id'        => ['required', 'sanitize:text'],
            'stock'     => ['required', 'sanitize:text'],
            'operation' => ['required', 'sanitize:text']
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $newStock = wc_update_product_stock(
            $fieldMappings['id'],
            (int) $fieldMappings['stock'],
            $fieldMappings['operation']
        );

        if (is_wp_error($newStock) || !$newStock) {
            return [
                'response' => isset($newStock->errors)
                    ? $newStock->get_error_messages()
                    : __('Failed to update product stock.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 400,
            ];
        }

        return [
            'response'    => ['new_stock' => $newStock],
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Delete product.
     *
     * @param bool $force
     */
    public function deleteProduct($force = false): array
    {
        $productId = $this->nodeInfoProvider->getFieldMapData()['id'] ?? null;
        $payload = ['product_id' => $productId];

        $rules = [
            'product_id' => ['required', 'integer']
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($payload, $rules);

        if ($validation) {
            return $validation;
        }

        $product = wc_get_product($productId);

        if (empty($product)) {
            return [
                'response'    => __('Product not found with the given ID.', 'bit-pi'),
                'payload'     => $payload,
                'status_code' => 404,
            ];
        }

        $response = $product->delete($force)
            ? __('Product deleted successfully.', 'bit-pi')
            : __('Failed to delete product.', 'bit-pi');

        return [
            'response'    => $response,
            'payload'     => $payload,
            'status_code' => 200,
        ];
    }

    /**
     * Retrieve products totals.
     */
    public function getProductsTotals(): array
    {
        $productTypes = [
            'external' => 'External/Affiliate product',
            'grouped'  => 'Grouped product',
            'simple'   => 'Simple product',
            'variable' => 'Variable product',
        ];

        $result = [];
        foreach ($productTypes as $slug => $value) {
            $products = WooCommerceActionHelper::getProducts($slug);

            $result[] = [
                'type_slug'    => $slug,
                'product_type' => $value,
                'count'        => \count($products) ?? 0,
                'products'     => $products,
            ];
        }

        return [
            'response'    => $result,
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    /**
     * Retrieve product sales count by ID.
     */
    public function getProductSalesCountById(): array
    {
        $productId = $this->nodeInfoProvider->getFieldMapData()['id'] ?? null;
        $payload = ['product_id' => $productId];

        $rules = [
            'product_id' => ['required',  'integer']
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($payload, $rules);

        if ($validation) {
            return $validation;
        }

        $product = wc_get_product($productId);

        if (empty($product)) {
            return [
                'response'    => __('Product not found with the given ID.', 'bit-pi'),
                'payload'     => $payload,
                'status_code' => 404,
            ];
        }

        $report = WooCommerceActionHelper::getProductOrderStatusCount($product);

        return [
            'response'    => $report,
            'payload'     => $payload,
            'status_code' => 200,
        ];
    }

    /**
     * Update product status.
     */
    public function updateProductStatus(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'id'     => ['required', 'sanitize:text'],
            'status' => ['required', 'sanitize:text']
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $product = wc_get_product($fieldMappings['id']);

        if (empty($product)) {
            return [
                'response'    => __('Product not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $product->set_status($fieldMappings['status']);
        $product->save();

        $response = $product->get_status() === $fieldMappings['status']
            ? __('Product status updated successfully.', 'bit-pi')
            : __('Failed to update product status.', 'bit-pi');

        return [
            'response'    => $response,
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Create a Term by taxonomy.
     *
     * @param string $taxonomy
     *
     * @return collection
     */
    public function createTermByTax($taxonomy)
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'name' => ['required', 'sanitize:text']
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $term = wp_insert_term(
            $fieldMappings['name'],
            $taxonomy,
            $fieldMappings
        );

        if (is_wp_error($term)) {
            return [
                'response'    => $term->get_error_message(),
                'payload'     => $fieldMappings,
                'status_code' => $term->get_error_code(),
            ];
        }

        return [
            'response'    => $term,
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Update a Product Category.
     *
     * @param string $taxonomy
     *
     * @return collection
     */
    public function updateTermByTax($taxonomy)
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'id' => ['required', 'integer']
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $termId = $fieldMappings['id'];

        if (empty(get_term($termId, $taxonomy))) {
            return [
                'response'    => __('Term not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $term = wp_update_term($termId, $taxonomy, array_filter($fieldMappings));

        if (is_wp_error($term)) {
            return [
                'response'    => $term->get_error_message(),
                'payload'     => $fieldMappings,
                'status_code' => $term->get_error_code(),
            ];
        }

        return [
            'response'    => $term,
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Delete a Term by Taxonomy.
     *
     * @param string $taxonomy
     *
     * @return collection
     */
    public function deleteTermByTax($taxonomy)
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'id' => ['required', 'integer']
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $termId = $fieldMappings['id'];

        if (empty(get_term($termId, $taxonomy))) {
            return [
                'response'    => __('Term not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $status = wp_delete_term($termId, $taxonomy);

        if (!$status) {
            return [
                'response'    => __('Failed to delete term.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 500,
            ];
        }

        return [
            'response'    => __('Term deleted successfully.', 'bit-pi'),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Get all terms.
     *
     * @param null|string $taxonomy
     *
     * @return array
     */
    public static function getAllTerm($taxonomy = null)
    {
        return [
            'response'    => WooCommerceActionHelper::getTerms($taxonomy),
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    /**
     * Get a term by id.
     *
     * @param string $taxonomy
     *
     * @return collection
     */
    public function getTermById($taxonomy)
    {
        $categoryId = $this->nodeInfoProvider->getFieldMapData()['id'] ?? null;

        $payload = ['category_id' => $categoryId];

        $rules = [
            'category_id' => ['required', 'integer']
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($payload, $rules);

        if ($validation) {
            return $validation;
        }

        return [
            'response'    => WooCommerceActionHelper::getTerms($taxonomy, $categoryId),
            'payload'     => $payload,
            'status_code' => 200,
        ];
    }

    /**
     * Add product attribute.
     *
     * @return collection
     */
    public function addProductAttribute()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'id'     => ['required', 'sanitize:text'],
            'name'   => ['required', 'sanitize:text'],
            'values' => ['required', 'sanitize:text']
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $product = wc_get_product($fieldMappings['id']);

        if (empty($product)) {
            return [
                'response'    => __('Product not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $values = array_map('trim', explode(',', $fieldMappings['values']));

        $attributes = $product->get_attributes();

        $newAttribute = new WC_Product_Attribute();

        $newAttribute->set_id(0);
        $newAttribute->set_name($fieldMappings['name']);
        $newAttribute->set_options($values);
        $newAttribute->set_position(\count($attributes) + 1);
        $newAttribute->set_visible($fieldMappings['visible'] ?? true);
        $newAttribute->set_variation($fieldMappings['used_for_variations'] ?? false);

        $attributes[$fieldMappings['name']] = $newAttribute;

        $product->set_attributes($attributes);

        if (!$product->save()) {
            return [
                'response'    => __('Failed to add product attribute.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 500,
            ];
        }

        return [
            'response'    => $product->get_data(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Remove product attribute.
     *
     * @return collection
     */
    public function removeProductAttribute()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'id'    => ['required', 'sanitize:text'],
            'names' => ['required', 'sanitize:text'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $product = wc_get_product($fieldMappings['id']);

        if (empty($product)) {
            return [
                'response'    => __('Product not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $attributes = $product->get_attributes();
        $namesToRemove = array_map('trim', explode(',', $fieldMappings['names']));

        foreach ($attributes as $key => $attribute) {
            if (\in_array($attribute->get_name(), $namesToRemove, true)) {
                unset($attributes[$key]);
            }
        }

        $product->set_attributes($attributes);

        if (!$product->save()) {
            return [
                'response'    => __('Failed to remove product attribute.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 500,
            ];
        }

        return [
            'response'    => $product->get_data(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Create an attribute.
     *
     * @return collection
     */
    public function createAttribute()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'name' => ['required', 'sanitize:text']
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $attribute = wc_create_attribute($fieldMappings);

        if (is_wp_error($attribute)) {
            return [
                'response'    => $attribute->get_error_message(),
                'payload'     => $fieldMappings,
                'status_code' => $attribute->get_error_code(),
            ];
        }

        return [
            'response'    => wc_get_attribute($attribute),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Get an attribute.
     *
     * @return collection
     */
    public function getAttribute()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'id' => ['required', 'integer']
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        return [
            'response'    => wc_get_attribute($fieldMappings['id']),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Update an attribute.
     *
     * @return collection
     */
    public function updateAttribute()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'id' => ['required', 'integer']
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $attribute = wc_update_attribute($fieldMappings['id'], array_filter($fieldMappings));

        return [
            'response'    => wc_get_attribute($attribute),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Delete an attribute.
     *
     * @return collection
     */
    public function deleteAttribute()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'id' => ['required', 'integer']
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $status = wc_delete_attribute($fieldMappings['id']);

        if (is_wp_error($status)) {
            return [
                'response'    => $status->get_error_message(),
                'payload'     => $fieldMappings,
                'status_code' => $status->get_error_code(),
            ];
        }

        if (!$status) {
            return [
                'response'    => __('Failed to delete attribute.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 500,
            ];
        }

        return [
            'response'    => __('Attribute deleted successfully.', 'bit-pi'),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Get cart totals.
     *
     * @return collection
     */
    public function getCartTotals()
    {
        return [
            'response'    => WC()->cart->get_totals(),
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    /**
     * Get all cart items.
     *
     * @return collection
     */
    public function getAllCartItems()
    {
        $cartItems = array_map(
            function ($item) {
                $item['data'] = $item['data']->get_data();

                return $item;
            },
            WC()->cart->get_cart()
        );

        return [
            'response'    => $cartItems,
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    /**
     * Add a product to the cart.
     *
     * @return collection
     */
    public function addProductToCart()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'product_id'       => ['required', 'sanitize:text'],
            'product_quantity' => ['required', 'sanitize:text']
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        WC()->cart->add_to_cart($fieldMappings['product_id'], $fieldMappings['product_quantity']);

        return [
            'response'    => WC()->cart->get_cart(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Remove a product to the cart.
     *
     * @return collection
     */
    public function removeProductFromCart()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'product_id' => ['required', 'integer']
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $productCartId = WC()->cart->generate_cart_id($fieldMappings['product_id']);
        $cartItemKey = WC()->cart->find_product_in_cart($productCartId);

        if (empty($cartItemKey)) {
            return [
                'response'    => __('Product not found in cart with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 400,
            ];
        }

        WC()->cart->remove_cart_item($cartItemKey);

        wc_add_notice($fieldMappings['message']);

        return [
            'response'    => WC()->cart->get_cart(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Get all coupons.
     *
     * @return collection
     */
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
                return WooCommerceActionHelper::getCouponData($post->ID);
            },
            $posts
        );

        return [
            'response'    => $coupons,
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    /**
     * Get coupon by id.
     *
     * @return collection
     */
    public function getCouponById()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'id' => ['required', 'integer']
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        return [
            'response'    => WooCommerceActionHelper::getCouponData($fieldMappings['id']),
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    /**
     * Retrieve coupons totals.
     *
     * @return collection
     */
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
                ++$data[$type]['totals'];
                $data[$type]['coupons'][] = $coupon->get_data();
            }
        }

        return [
            'response'    => $data,
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    /**
     * Create Coupon.
     *
     * @return collection
     */
    public function createCoupon()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'discount_type' => ['required', 'sanitize:text'],
            'coupon_code'   => ['required', 'sanitize:text'],
            'coupon_amount' => ['required', 'sanitize:text'],
            'expiry_date'   => ['required', 'sanitize:text']
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
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

        $fieldMappings = WooCommerceActionHelper::formatCouponBooleanFields($fieldMappings);
        $fieldMappings['customer_email'] = WooCommerceActionHelper::sanitizeCustomerEmails($fieldMappings['customer_email'] ?? '');

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
            'response'    => WooCommerceActionHelper::getCouponData($couponId),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    /**
     * Update Coupon.
     *
     * @return collection
     */
    public function updateCoupon()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'coupon_code' => ['required', 'sanitize:text'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $coupon = new WC_Coupon($fieldMappings['coupon_code']);

        if (empty($coupon->get_id())) {
            return [
                'response'    => __('Coupon not found with the given code.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $fieldMappings = WooCommerceActionHelper::formatCouponBooleanFields($fieldMappings);

        if (isset($fieldMappings['customer_email'])) {
            $fieldMappings['customer_email'] = WooCommerceActionHelper::sanitizeCustomerEmails($fieldMappings['customer_email'] ?? '');
        }

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

    /**
     * Update Coupon code.
     *
     * @return collection
     */
    public function updateCouponCode()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'coupon_id'   => ['required', 'sanitize:text'],
            'coupon_code' => ['required', 'sanitize:text'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
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

    /**
     * Add Emails to Coupon.
     *
     * @return collection
     */
    public function addEmailsToCoupon()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'coupon_code_or_id' => ['required', 'sanitize:text'],
            'customer_email'    => ['required', 'sanitize:text'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
        }

        $coupon = new WC_Coupon($fieldMappings['coupon_code_or_id']);

        if (empty($coupon->get_id())) {
            return [
                'response'    => __('Coupon not found with the given code or id.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $customerEmails = WooCommerceActionHelper::sanitizeCustomerEmails($fieldMappings['customer_email']);

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

    /**
     * Delete Coupon.
     *
     * @return collection
     */
    public function deleteCoupon()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'coupon_code_or_id' => ['required', 'sanitize:text'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
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
            $coupon->set_status('trash');
            $status = $coupon->save();
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

    /**
     * Apply Coupon to Cart.
     *
     * @return collection
     */
    public function applyCouponToCart()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'coupon_code_or_id' => ['required', 'sanitize:text'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
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

    /**
     * Get Applied Coupons.
     *
     * @return collection
     */
    public function getAppliedCoupons()
    {
        return [
            'response'    => WC()->cart->get_applied_coupons(),
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    /**
     * Remove Coupon from Cart.
     *
     * @return collection
     */
    public function removeCouponFromCart()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $rules = [
            'coupon_code_or_id' => ['required', 'sanitize:text'],
        ];

        $validation = WooCommerceActionHelper::validateFieldMap($fieldMappings, $rules);

        if ($validation) {
            return $validation;
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

    /**
     * Get All Reviews.
     *
     * @return collection
     */
    public function getAllReviews()
    {
        $approvedReviews = get_comments(
            [
                'post_type' => 'product',
                'status'    => 'approve',
                'meta_key'  => 'rating', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required to filter reviews by rating
            ]
        );

        $ratingSummary = [];
        $reviewsData = [];

        foreach ($approvedReviews as $review) {
            $commentId = $review->comment_ID;

            if (empty($commentId)) {
                continue;
            }

            $rating = (int) get_comment_meta($commentId, 'rating', true);

            if ($rating > 0 && $rating <= 5) {
                // Increment the count for this rating in the summary
                $ratingSummary[$rating] = isset($ratingSummary[$rating])
                    ? $ratingSummary[$rating] + 1
                    : 1;

                $reviewsData[$rating][] = [
                    'rating'       => $rating,
                    'comment_data' => Utility::jsonEncodeDecode($review),
                    'user_data'    => Utility::getUserInfo($review->user_id),
                ];
            }
        }

        $ratingLabels = [
            1 => 'Rated 1 out of 5 stars',
            2 => 'Rated 2 out of 5 stars',
            3 => 'Rated 3 out of 5 stars',
            4 => 'Rated 4 out of 5 stars',
            5 => 'Rated 5 out of 5 stars',
        ];

        $response = [];
        foreach ($ratingLabels as $ratingNumber => $ratingLabel) {
            $response[] = [
                'slug'    => "rated_{$ratingNumber}_out_of_5",
                'name'    => $ratingLabel,
                'total'   => $ratingSummary[$ratingNumber] ?? 0,
                'reviews' => $reviewsData[$ratingNumber] ?? [],
            ];
        }

        return [
            'response'    => $response,
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    /**
     * Get Top Sellers Report.
     *
     * @return collection
     */
    public function topSellingProductsReport()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $fieldMappings['limit'] = $fieldMappings['limit'] ?? 10;

        if (isset($fieldMappings['before'])) {
            $fieldMappings['before'] = $fieldMappings['before'] . ' 23:59:59';
        }

        if (isset($fieldMappings['after'])) {
            $fieldMappings['after'] = $fieldMappings['after'] . ' 00:00:00';
        }

        if (!class_exists('Automattic\WooCommerce\Admin\API\Reports\Products\Query')) {
            return [
                'response'    => 'WooCommerce Admin is not activated. Please activate it to use this feature.',
                'payload'     => [],
                'status_code' => 500,
            ];
        }

        $reportQuery = new Query($fieldMappings);
        $reportData = $reportQuery->get_data();

        $topSellingProducts = [];

        $productData = $reportData->data ?? $reportData['data'] ?? [];


        foreach ($productData as $productEntry) {
            if (\is_array($productEntry)) {
                $productEntry = (object) $productEntry;
            }

            $product = wc_get_product($productEntry->product_id);

            if ($product) {
                $topSellingProducts[] = [
                    'title'        => $product->get_name(),
                    'product_id'   => (int) $productEntry->product_id,
                    'quantity'     => (int) $productEntry->items_sold,
                    'net_revenue'  => (int) $productEntry->net_revenue,
                    'orders_count' => (int) $productEntry->orders_count,
                    'product_data' => $product->get_data(),
                ];
            }
        }

        return [
            'response'    => $topSellingProducts,
            'payload'     => [],
            'status_code' => 200,
        ];
    }
}
