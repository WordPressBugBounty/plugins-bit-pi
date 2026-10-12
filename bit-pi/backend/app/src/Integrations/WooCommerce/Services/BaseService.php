<?php

namespace BitApps\Pi\src\Integrations\WooCommerce\Services;

use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Integrations\IntegrationHelper;
use Exception;
use WC_Customer;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

abstract class BaseService
{
    protected $nodeInfoProvider;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    protected function findCustomer($customerId)
    {
        try {
            $customer = new WC_Customer((int) $customerId);
        } catch (Exception $exception) {
            return;
        }

        return $customer->get_id() ? $customer : null;
    }

    protected function isCustomerAccount($customer)
    {
        $user = get_userdata($customer->get_id());

        return $user && \in_array('customer', (array) $user->roles, true) && !user_can($user, 'edit_posts');
    }

    protected function findOrder($orderId)
    {
        $order = wc_get_order((int) $orderId);

        return $order instanceof WC_Order ? $order : null;
    }

    protected function validateDateRange($dateMin, $dateMax, array $fieldMappings)
    {
        foreach ([$dateMin, $dateMax] as $date) {
            if (!empty($date) && !$this->isValidDate((string) $date)) {
                return [
                    'response'    => __('Dates must use the YYYY-MM-DD format, for example 2026-01-31.', 'bit-pi'),
                    'payload'     => $fieldMappings,
                    'status_code' => 422,
                ];
            }
        }

        if (!empty($dateMin) && !empty($dateMax) && strtotime((string) $dateMin) > strtotime((string) $dateMax)) {
            return [
                'response'    => __('The start date must be on or before the end date.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 422,
            ];
        }
    }

    protected function parseEmails($emails)
    {
        return array_values(array_filter(array_map('trim', explode(',', $emails))));
    }

    protected function createTerm($taxonomy)
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'name' => ['required']
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
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

    protected function updateTerm($taxonomy)
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'id' => ['required', 'integer']
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
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

    protected function deleteTerm($taxonomy)
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'id' => ['required', 'integer']
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
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

    protected function getAllTerms($taxonomy)
    {
        return [
            'response'    => get_terms(['taxonomy' => $taxonomy, 'orderby' => 'term_id', 'hide_empty' => false]),
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    protected function getTerm($taxonomy)
    {
        $categoryId = $this->nodeInfoProvider->getFieldMapData()['id'] ?? null;

        $payload = ['category_id' => $categoryId];

        $validationRules = [
            'category_id' => ['required', 'integer']
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($payload, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        return [
            'response'    => get_term($categoryId, $taxonomy),
            'payload'     => $payload,
            'status_code' => 200,
        ];
    }

    private function isValidDate($date)
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }
}
