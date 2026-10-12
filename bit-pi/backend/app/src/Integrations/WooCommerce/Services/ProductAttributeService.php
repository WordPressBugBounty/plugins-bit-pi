<?php

namespace BitApps\Pi\src\Integrations\WooCommerce\Services;

use BitApps\Pi\src\Integrations\IntegrationHelper;
use WC_Product_Attribute;

if (!defined('ABSPATH')) {
    exit;
}

final class ProductAttributeService extends BaseService
{
    public function addProductAttribute()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'id'     => ['required'],
            'name'   => ['required'],
            'values' => ['required']
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
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

    public function removeProductAttribute()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'id'    => ['required'],
            'names' => ['required'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
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

    public function createAttribute()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'name' => ['required']
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
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

    public function updateAttribute()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'id' => ['required', 'integer']
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $attribute = wc_update_attribute($fieldMappings['id'], array_filter($fieldMappings));

        return [
            'response'    => wc_get_attribute($attribute),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function getAttribute()
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
            'response'    => wc_get_attribute($fieldMappings['id']),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function deleteAttribute()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'id' => ['required', 'integer']
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
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

    public function addAttributeTerms(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();
        $fieldMappings['attribute_terms'] = $this->nodeInfoProvider->getFieldMapRepeaters('attribute-terms.value', false, false);

        $validationRules = ['attribute_id' => ['required', 'integer']];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $attribute = wc_get_attribute((int) $fieldMappings['attribute_id']);

        if (!$attribute) {
            return [
                'response'    => __('Attribute not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $taxonomy = $attribute->slug;
        $createdTerms = [];
        $errors = [];

        foreach ((array) $fieldMappings['attribute_terms'] as $attributeTerm) {
            if (empty($attributeTerm['name'])) {
                continue;
            }

            $termArgs = [
                'slug'        => $attributeTerm['slug'] ?? '',
                'description' => $attributeTerm['description'] ?? '',
            ];

            $result = wp_insert_term($attributeTerm['name'], $taxonomy, $termArgs);

            if (is_wp_error($result)) {
                $errors[] = ['name' => $attributeTerm['name'], 'error' => $result->get_error_message()];
            } else {
                $createdTerms[] = get_term($result['term_id'], $taxonomy);
            }
        }

        $response = [
            'attribute'     => $attribute,
            'created_terms' => $createdTerms,
            'errors'        => $errors,
        ];

        return [
            'response'    => $response,
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }
}
