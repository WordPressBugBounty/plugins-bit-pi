<?php

namespace BitApps\Pi\src\Integrations\WooCommerce\Services;

use BitApps\Pi\src\Integrations\IntegrationHelper;
use WC_Customer;

if (!defined('ABSPATH')) {
    exit;
}

final class CustomerService extends BaseService
{
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

    public function getCustomerById()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'id' => ['required', 'integer'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $customer = $this->findCustomer($fieldMappings['id']);

        if (empty($customer)) {
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

    public function getCustomerByEmail()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'email' => ['required'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
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

    public function createNewCustomer()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $fieldMappings['billing_address'] = $this->nodeInfoProvider->getFieldMapRepeaters('billing-field-map.value', false, true, 'billing_field', 'value');
        $fieldMappings['shipping_address'] = $this->nodeInfoProvider->getFieldMapRepeaters('shipping-field-map.value', false, true, 'shipping_field', 'value');

        $generatePassword = $this->nodeInfoProvider->getFieldMapConfigs('generate-password.value') ?? false;
        $shippingSameAsBilling = $this->nodeInfoProvider->getFieldMapConfigs('shipping-same-as-billing.value') ?? false;

        $validationRules = [
            'email' => ['required'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $username = $fieldMappings['username'] ?? current(explode('@', $fieldMappings['email']));

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

    public function getCustomersTotals(): array
    {
        $args = [
            'role'   => 'customer',
            'fields' => 'ID',
        ];

        $customers = get_users($args);
        $lastMonth = strtotime('-30 days');
        $lastYear = strtotime('-365 days');

        $response = [
            'total_customers'       => \count($customers),
            'customers_last_30days' => \count(get_users(array_merge($args, ['date_query' => [['after' => gmdate('Y-m-d', $lastMonth)]]]))),
            'customers_last_year'   => \count(get_users(array_merge($args, ['date_query' => [['after' => gmdate('Y-m-d', $lastYear)]]]))),
        ];

        return [
            'response'    => $response,
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    public function updateCustomerById(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();
        $fieldMappings['billing_address'] = $this->nodeInfoProvider->getFieldMapRepeaters('billing-field-map.value', false, true, 'billing_field', 'value');
        $fieldMappings['shipping_address'] = $this->nodeInfoProvider->getFieldMapRepeaters('shipping-field-map.value', false, true, 'shipping_field', 'value');

        $shippingSameAsBilling = $this->nodeInfoProvider->getFieldMapConfigs('shipping-same-as-billing.value') ?? false;

        $validationRules = [
            'customer_id' => ['required', 'integer'],
            'email'       => ['nullable', 'email'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $customer = $this->findCustomer($fieldMappings['customer_id']);

        if (empty($customer)) {
            return [
                'response'    => __('Customer not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        if (!$this->isCustomerAccount($customer)) {
            return [
                'response'    => __('This action only updates users with the Customer role, never staff accounts.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 403,
            ];
        }

        $profileSetters = [
            'first_name'   => 'set_first_name',
            'last_name'    => 'set_last_name',
            'display_name' => 'set_display_name',
            'email'        => 'set_email',
        ];

        foreach ($profileSetters as $key => $setter) {
            if (!empty($fieldMappings[$key])) {
                $customer->{$setter}($fieldMappings[$key]);
            }
        }

        $addresses = [
            'billing'  => $fieldMappings['billing_address'],
            'shipping' => $shippingSameAsBilling ? $fieldMappings['billing_address'] : $fieldMappings['shipping_address'],
        ];

        foreach ($addresses as $addressType => $address) {
            foreach ((array) $address as $key => $value) {
                $setter = "set_{$addressType}_{$key}";

                if (\is_callable([$customer, $setter])) {
                    $customer->{$setter}($value);
                }
            }
        }

        $customer->save();

        return [
            'response'    => $customer->get_data(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function deleteCustomerById(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = ['customer_id' => ['required', 'integer']];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $customer = $this->findCustomer($fieldMappings['customer_id']);

        if (empty($customer)) {
            return [
                'response'    => __('Customer not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        if (!$this->isCustomerAccount($customer)) {
            return [
                'response'    => __('This action only deletes users with the Customer role, never staff accounts.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 403,
            ];
        }

        if (!\function_exists('wp_delete_user')) {
            include_once ABSPATH . 'wp-admin/includes/user.php';
        }

        $deleted = $customer->delete();

        if (!$deleted) {
            return [
                'response'    => __('Failed to delete the customer.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 500,
            ];
        }

        return [
            'response'    => __('Customer deleted successfully.', 'bit-pi'),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function getCustomersByPurchasedProduct(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'product_id' => ['required', 'integer'],
            'date_min'   => ['required', 'string'],
            'date_max'   => ['required', 'string'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $productId = (int) $fieldMappings['product_id'];
        $product = wc_get_product($productId);

        if (empty($product)) {
            return [
                'response'    => __('Product not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $dateMin = $fieldMappings['date_min'];
        $dateMax = $fieldMappings['date_max'];

        $dateValidation = $this->validateDateRange($dateMin, $dateMax, $fieldMappings);

        if ($dateValidation) {
            return $dateValidation;
        }

        $orderIds = $this->getPurchasedProductOrderIds($dateMin, $dateMax);

        if (empty($orderIds)) {
            return $this->buildPurchasedProductResponse([], $dateMin, $dateMax, $productId, $product, $fieldMappings);
        }

        $matchedOrderIds = $this->getPurchasedProductMatchedOrderIds($orderIds, $productId);

        if (empty($matchedOrderIds)) {
            return $this->buildPurchasedProductResponse([], $dateMin, $dateMax, $productId, $product, $fieldMappings);
        }

        $customers = $this->buildPurchasedProductCustomers($orderIds, $matchedOrderIds);

        return $this->buildPurchasedProductResponse($customers, $dateMin, $dateMax, $productId, $product, $fieldMappings);
    }

    public function getCustomerTotalSpent(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'customer_id' => ['required', 'integer'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $customer = $this->findCustomer($fieldMappings['customer_id']);

        if (empty($customer)) {
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

    public function getCustomerLastOrder(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'customer_id' => ['required', 'integer'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $customer = $this->findCustomer($fieldMappings['customer_id']);

        if (empty($customer)) {
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

    public function getCustomerOrderCount(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = ['customer_id' => ['required', 'integer']];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $customer = $this->findCustomer($fieldMappings['customer_id']);

        if (empty($customer)) {
            return [
                'response'    => __('Customer not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $response = [
            'customer_id' => $customer->get_id(),
            'email'       => $customer->get_email(),
            'order_count' => $customer->get_order_count(),
            'total_spent' => $customer->get_total_spent(),
        ];

        return [
            'response'    => $response,
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    private function getPurchasedProductOrderIds(string $dateMin, string $dateMax): array
    {
        return wc_get_orders(
            [
                'date_after'  => $dateMin . ' 00:00:00',
                'date_before' => $dateMax . ' 23:59:59',
                'limit'       => -1,
                'order'       => 'DESC',
                'orderby'     => 'date',
                'return'      => 'ids',
                'status'      => wc_get_is_paid_statuses(),
                'type'        => 'shop_order',
            ]
        );
    }

    private function getPurchasedProductMatchedOrderIds(array $orderIds, int $productId): array
    {
        global $wpdb;

        $matchedOrderIds = [];

        foreach (array_chunk(array_values(array_unique(array_map('intval', $orderIds))), 500) as $orderIdChunk) {
            if (empty($orderIdChunk)) {
                continue;
            }

            $placeholders = implode(',', array_fill(0, \count($orderIdChunk), '%d'));

            $query = "
                SELECT DISTINCT oi.order_id
                FROM {$wpdb->prefix}woocommerce_order_items oi
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
                    ON oi.order_item_id = oim.order_item_id
                WHERE oi.order_id IN ({$placeholders})
                    AND oi.order_item_type = %s
                    AND (
                        (oim.meta_key = %s AND CAST(oim.meta_value AS UNSIGNED) = %d)
                        OR (oim.meta_key = %s AND CAST(oim.meta_value AS UNSIGNED) = %d)
                    )
            ";

            $rows = $wpdb->get_col(
                $wpdb->prepare(
                    $query,
                    ...array_merge($orderIdChunk, ['line_item', '_product_id', $productId, '_variation_id', $productId])
                )
            );

            if (!empty($rows)) {
                $matchedOrderIds = array_merge($matchedOrderIds, array_map('intval', $rows));
            }
        }

        return array_values(array_unique($matchedOrderIds));
    }

    private function buildPurchasedProductCustomers(array $orderIds, array $matchedOrderIds): array
    {
        $matchedOrderLookup = array_fill_keys($matchedOrderIds, true);
        $customersByKey = [];
        $resolvedCustomerIdsByEmail = [];

        foreach ($orderIds as $orderId) {
            $orderId = (int) $orderId;

            if (!isset($matchedOrderLookup[$orderId])) {
                continue;
            }

            $order = wc_get_order($orderId);

            if (empty($order)) {
                continue;
            }

            $customerId = (int) $order->get_customer_id();
            $email = $order->get_billing_email();

            if (empty($email) && empty($customerId)) {
                continue;
            }

            if (empty($customerId) && !empty($email)) {
                if (!\array_key_exists($email, $resolvedCustomerIdsByEmail)) {
                    $user = get_user_by('email', $email);
                    $resolvedCustomerIdsByEmail[$email] = $user ? (int) $user->ID : 0;
                }

                $customerId = $resolvedCustomerIdsByEmail[$email];

                if (empty($customerId)) {
                    continue;
                }
            }

            $customerKey = 'customer_' . $customerId;

            if (!isset($customersByKey[$customerKey])) {
                $customer = $this->findCustomer($customerId);

                if (empty($customer)) {
                    continue;
                }

                $orderDate = $order->get_date_created();

                $customersByKey[$customerKey] = array_merge(
                    (array) $customer->get_data(),
                    [
                        'order_ids'       => [],
                        'orders_count'    => 0,
                        'last_order_date' => $orderDate ? $orderDate->date('Y-m-d H:i:s') : null,
                    ],
                );
            }

            $customersByKey[$customerKey]['order_ids'][] = $order->get_id();
            $customersByKey[$customerKey]['orders_count'] = \count($customersByKey[$customerKey]['order_ids']);
        }

        return array_values($customersByKey);
    }

    private function buildPurchasedProductResponse(array $customers, string $dateMin, string $dateMax, int $productId, object $product, array $fieldMappings): array
    {
        $customerEmails = array_values(
            array_filter(
                array_map(
                    static function ($customer) {
                        return $customer['email'] ?? null;
                    },
                    $customers
                )
            )
        );

        return [
            'response' => [
                'customers_count' => \count($customers),
                'date_max'        => $dateMax,
                'date_min'        => $dateMin,
                'product_id'      => $productId,
                'product_name'    => $product->get_name(),
                'customer_emails' => $customerEmails,
                'customers'       => $customers,
            ],
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }
}
