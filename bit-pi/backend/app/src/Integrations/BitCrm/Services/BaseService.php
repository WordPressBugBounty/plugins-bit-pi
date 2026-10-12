<?php

namespace BitApps\Pi\src\Integrations\BitCrm\Services;

use BitApps\Crm\Model\LineItem;
use BitApps\CrmPro\Model\CustomField;
use BitApps\CrmPro\Model\Product;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Integrations\BitCrm\BitCrmHelper;

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

        // Custom fields travel in their own payload key, so they must not reach
        // the entity's own columns.
        $mapped = array_filter(
            $this->mappedFields(),
            static function ($key) {
                return strpos((string) $key, BitCrmHelper::CUSTOM_FIELD_PREFIX) !== 0;
            },
            ARRAY_FILTER_USE_KEY
        );

        return array_merge($mapped, $filteredExtra);
    }

    /**
     * Field map rows targeting a custom field, keyed the way Bit CRM's entity
     * services expect: [field_key => ['field_id' => int, 'field_value' => string]].
     *
     * Rows whose field no longer exists on the module are dropped, so deleting a
     * custom field in Bit CRM cannot break a flow that still maps it.
     */
    protected function customFieldsValues(string $module): array
    {
        $definitions = BitCrmHelper::customFields($module);

        if (empty($definitions)) {
            return [];
        }

        $byKey = array_column($definitions, null, 'field_key');
        $values = [];

        foreach ($this->mappedFields() as $key => $value) {
            if (strpos((string) $key, BitCrmHelper::CUSTOM_FIELD_PREFIX) !== 0) {
                continue;
            }

            $fieldKey = substr((string) $key, \strlen(BitCrmHelper::CUSTOM_FIELD_PREFIX));

            if (!isset($byKey[$fieldKey]) || $value === null || $value === '' || $value === []) {
                continue;
            }

            $values[$fieldKey] = [
                'field_id'    => (int) $byKey[$fieldKey]['id'],
                'field_value' => self::customFieldValue($value, (string) ($byKey[$fieldKey]['type'] ?? '')),
            ];
        }

        return $values;
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

    /**
     * Multi-value custom fields are stored as a JSON list; everything else is a
     * plain string. A mapped value arrives comma separated when it comes from a
     * text token rather than a real array.
     *
     * @param mixed $value
     */
    private static function customFieldValue($value, string $type): string
    {
        if (!\in_array($type, CustomField::MULTI_VALUE_FIELD_TYPES)) {
            return \is_array($value) ? implode(', ', $value) : (string) $value;
        }

        $items = \is_array($value) ? $value : explode(',', (string) $value);

        $items = array_values(
            array_filter(
                array_map('trim', array_map('strval', $items)),
                static function ($item) {
                    return $item !== '';
                }
            )
        );

        return (string) wp_json_encode($items);
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
