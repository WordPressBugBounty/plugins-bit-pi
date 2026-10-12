<?php

namespace BitApps\Pi\src\Integrations\WooCommerce\Services;

use Automattic\WooCommerce\Admin\API\Reports\Products\Query;
use Automattic\WooCommerce\Admin\API\Reports\Revenue\Query as RevenueQuery;

if (!defined('ABSPATH')) {
    exit;
}

final class ReportService extends BaseService
{
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
                'response'    => __('WooCommerce Admin is not activated. Please activate it to use this feature.', 'bit-pi'),
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

    public function getSalesReport()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $dateValidation = $this->validateDateRange($fieldMappings['date_min'] ?? '', $fieldMappings['date_max'] ?? '', $fieldMappings);

        if (!empty($dateValidation)) {
            return $dateValidation;
        }

        if (!class_exists(RevenueQuery::class)) {
            return [
                'response'    => __('WooCommerce Analytics is not available. Please enable it to use this feature.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 500,
            ];
        }

        $reportQuery = new RevenueQuery(
            [
                'after'    => (empty($fieldMappings['date_min']) ? '1970-01-01' : $fieldMappings['date_min']) . ' 00:00:00',
                'before'   => (empty($fieldMappings['date_max']) ? current_time('Y-m-d') : $fieldMappings['date_max']) . ' 23:59:59',
                'interval' => 'year',
                'per_page' => 1,
            ]
        );

        $totals = (array) ($reportQuery->get_data()->totals ?? []);

        $response = [
            'total_orders'    => $totals['orders_count'] ?? 0,
            'total_sales'     => $totals['total_sales'] ?? 0,
            'gross_sales'     => $totals['gross_sales'] ?? 0,
            'total_refunds'   => $totals['refunds'] ?? 0,
            'total_tax'       => $totals['taxes'] ?? 0,
            'total_shipping'  => $totals['shipping'] ?? 0,
            'total_discounts' => $totals['coupons'] ?? 0,
            'net_sales'       => $totals['net_revenue'] ?? 0,
        ];

        return [
            'response'    => $response,
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }
}
