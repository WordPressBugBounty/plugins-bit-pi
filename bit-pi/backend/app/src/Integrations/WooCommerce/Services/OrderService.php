<?php

namespace BitApps\Pi\src\Integrations\WooCommerce\Services;

use BitApps\Pi\src\Integrations\IntegrationHelper;
use BitApps\Pi\src\Integrations\WooCommerce\helper\WooCommerceHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class OrderService extends BaseService
{
    public function createOrder()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $fieldMappings['billingAddress'] = $this->nodeInfoProvider->getFieldMapRepeaters('billing-field-map.value', false, true, 'billing_field', 'value');
        $fieldMappings['shippingAddress'] = $this->nodeInfoProvider->getFieldMapRepeaters('shipping-field-map.value', false, true, 'shipping_field', 'value');

        $mapProductIds = $this->nodeInfoProvider->getFieldMapConfigs('map-product-ids.value') ?? false;
        $shippingSameAsBilling = $this->nodeInfoProvider->getFieldMapConfigs('shipping-same-as-billing.value') ?? false;

        $validationRules = [
            'status'                    => ['required'],
            'payment_method'            => ['required'],
            'payment_method_title'      => ['required'],
            'billingAddress.first_name' => ['required'],
            'billingAddress.last_name'  => ['required'],
            'billingAddress.email'      => ['required'],
            'billingAddress.phone'      => ['required'],
            'billingAddress.address_1'  => ['required'],
            'billingAddress.city'       => ['required'],
            'billingAddress.state'      => ['required'],
            'billingAddress.country'    => ['required'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
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

    public function updateOrderStatus(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'order_id' => ['required', 'integer'],
            'status'   => ['required'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $order = $this->findOrder($fieldMappings['order_id']);

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

    public function addOrUpdateOrderMeta(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();
        $fieldMappings['custom_fields_mapping'] = $this->nodeInfoProvider->getFieldMapRepeaters('custom-field-mapping.value', false, true, 'meta_key', 'value');

        $validationRules = [
            'order_id' => ['required', 'integer'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
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

    public function getOrderMetadata(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'order_id' => ['required', 'integer'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $order = wc_get_order((int) $fieldMappings['order_id']);

        if (empty($order)) {
            return [
                'response'    => __('Order not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $orderMetadata = array_map(
            function ($metadata) {
                $metadata = $metadata->get_data();
                $metadata['value'] = maybe_unserialize($metadata['value']);

                return $metadata;
            },
            $order->get_meta_data() ?: []
        );

        return [
            'response'    => $orderMetadata,
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function getOrderMetadataByMetaKey(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'order_id' => ['required', 'integer'],
            'meta_key' => ['required', 'string'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $order = wc_get_order((int) $fieldMappings['order_id']);

        if (empty($order)) {
            return [
                'response'    => __('Order not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $metaValue = $order->get_meta($fieldMappings['meta_key']);
        $metadata = [
            'meta_key' => $fieldMappings['meta_key'],
            'value'    => maybe_unserialize($metaValue),
        ];

        return [
            'response'    => $metadata,
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

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

    public function getAllOrders(): array
    {
        return [
            'response'    => $this->getOrders(),
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    public function getOrderByStatus(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'status' => ['required'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        return [
            'response'    => $this->getOrders($fieldMappings['status']),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function getOrderByBillingEmail(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'billing_email' => ['required'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        return [
            'response'    => $this->getOrders(null, null, $fieldMappings['billing_email']),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function getOrderByCustomerId(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'customer_id' => ['required', 'integer'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        return [
            'response'    => $this->getOrders(null, $fieldMappings['customer_id']),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function getOrderById(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'id' => ['required', 'integer'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
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

    public function addOrderNote()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'order_id' => ['required', 'integer'],
            'note'     => ['required'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $order = $this->findOrder($fieldMappings['order_id']);

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

    public function getOrderNotes(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = ['order_id' => ['required', 'integer']];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $order = $this->findOrder($fieldMappings['order_id']);

        if (empty($order)) {
            return [
                'response'    => __('Order not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $notesData = array_map(
            function ($note) {
                return [
                    'id'            => $note->id,
                    'content'       => $note->content,
                    'customer_note' => $note->customer_note,
                    'added_by'      => $note->added_by,
                    'date_created'  => WooCommerceHelper::formatDate($note->date_created),
                ];
            },
            wc_get_order_notes(['order_id' => $order->get_id()])
        );

        return [
            'response'    => $notesData,
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function getOrderRefunds(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = ['order_id' => ['required', 'integer']];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $order = $this->findOrder($fieldMappings['order_id']);

        if (empty($order)) {
            return [
                'response'    => __('Order not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $refundsData = array_map(
            function ($refund) {
                return $refund->get_data();
            },
            $order->get_refunds() ?? []
        );

        return [
            'response'    => $refundsData,
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    private function getOrders($status = null, $customerId = null, $billingEmail = null, $type = null): array
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
}
