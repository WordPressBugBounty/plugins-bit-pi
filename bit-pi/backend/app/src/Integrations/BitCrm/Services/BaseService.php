<?php

namespace BitApps\Pi\src\Integrations\BitCrm\Services;

use BitApps\Crm\Model\LineItem;
use BitApps\CrmPro\Model\Product;
use BitApps\Pi\src\Flow\NodeInfoProvider;

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

    protected function fields()
    {
        return $this->nodeInfoProvider->getFieldMapData();
    }

    protected function mappedFields()
    {
        $mapped = $this->nodeInfoProvider->getFieldMapRepeaters('field-map.value', false, true, 'crmField', 'value');

        return \is_array($mapped) ? $mapped : [];
    }

    protected function repeaterRows($repeaterId)
    {
        $rows = $this->nodeInfoProvider->getFieldMapRepeaters($repeaterId . '.value', false, false);

        return \is_array($rows) ? $rows : [];
    }

    protected function buildFieldValues(array $extra = [])
    {
        $filteredExtra = array_filter(
            $extra,
            static function ($value) {
                return $value !== null && $value !== '' && $value !== [];
            }
        );

        return array_merge($this->mappedFields(), $filteredExtra);
    }

    /**
     * Build line items from the "line-items" repeater.
     *
     * Each row maps a product id plus the catalogue that id belongs to, so a
     * single flow can mix Bit CRM and WooCommerce products. Name, code, price
     * and tax rate are read from the resolved product, and the row's own fields
     * override those defaults when they are filled in.
     *
     * @return array<int, array>
     */
    protected function lineItemsFromRepeater()
    {
        $rows = $this->repeaterRows('line-items');
        if (empty($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $productId = $row['product_id'] ?? null;

            if (empty($productId)) {
                continue;
            }

            $source = ($row['product_source'] ?? '') === LineItem::SOURCE_WOOCOMMERCE
                ? LineItem::SOURCE_WOOCOMMERCE
                : LineItem::SOURCE_PRODUCT;

            $product = $source === LineItem::SOURCE_WOOCOMMERCE
                ? self::findWooProduct($productId)
                : self::findCrmProduct($productId);

            if (empty($product)) {
                continue;
            }

            $items[] = [
                'product_id'          => (int) $productId,
                'product_source'      => $source,
                'product_name'        => $product['name'] ?? '',
                'product_code'        => $product['code'] ?? '',
                'unit_price'          => self::pick($row, 'unit_price', $product['price'] ?? 0),
                'quantity'            => self::pick($row, 'quantity', 1),
                'discount_percentage' => self::pick($row, 'discount_percentage', 0),
                'tax_rate'            => self::pick($row, 'tax_rate', $product['tax_rate'] ?? 0),
                'description'         => self::pick($row, 'description', $product['description'] ?? ''),
            ];
        }

        return $items;
    }

    private static function findCrmProduct($productId)
    {
        if (!class_exists('BitApps\CrmPro\Model\Product')) {
            return [];
        }

        $product = Product::findOne(['id' => (int) $productId, 'is_trash' => 0]);

        if (empty($product)) {
            return [];
        }

        return \is_object($product) && method_exists($product, 'toArray') ? $product->toArray() : (array) $product;
    }

    private static function findWooProduct($productId)
    {
        if (!\function_exists('wc_get_product')) {
            return [];
        }

        $product = wc_get_product((int) $productId);

        if (!$product) {
            return [];
        }

        return [
            'name'        => $product->get_name(),
            'code'        => $product->get_sku(),
            'price'       => $product->get_price(),
            'tax_rate'    => self::wooTaxRate($product),
            'description' => $product->get_short_description() ?: $product->get_description(),
        ];
    }

    /**
     * Tax rate of a WooCommerce product, derived the same way Bit CRM derives it
     * for its own WooCommerce line items.
     *
     * @param mixed $product
     *
     * @return float
     */
    private static function wooTaxRate($product)
    {
        if (!\function_exists('wc_tax_enabled') || !wc_tax_enabled() || $product->get_tax_status() !== 'taxable') {
            return 0;
        }

        if (!\function_exists('wc_get_price_including_tax') || !\function_exists('wc_get_price_excluding_tax')) {
            return 0;
        }

        $excludingTax = (float) wc_get_price_excluding_tax($product, ['qty' => 1]);

        if ($excludingTax <= 0) {
            return 0;
        }

        $includingTax = (float) wc_get_price_including_tax($product, ['qty' => 1]);

        return ($includingTax - $excludingTax) / $excludingTax * 100;
    }

    private static function pick(array $row, $key, $fallback)
    {
        return isset($row[$key]) && $row[$key] !== '' && $row[$key] !== [] ? $row[$key] : $fallback;
    }
}
