<?php

namespace BitApps\Pi\src\Integrations\BitCrm\Services;

use BitApps\CrmPro\Model\Product;
use BitApps\Pi\Deps\BitApps\WPKit\Hooks\Hooks;
use BitApps\Pi\src\Integrations\BitCrm\BitCrmHelper;
use BitApps\Pi\src\Integrations\IntegrationHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class ProductService extends BaseService
{
    public function createProduct()
    {
        if (!BitCrmHelper::isProActive()) {
            return ['status_code' => 400, 'payload' => [], 'response' => __('Bit CRM Pro (Product module) is not installed or activated', 'bit-pi')];
        }

        $fields = $this->fields();
        $systemValues = $this->buildFieldValues(
            [
                'type'   => $fields['type'] ?? null,
                'brand'  => $fields['brand'] ?? null,
                'status' => $fields['status'] ?? null,
            ]
        );

        $error = IntegrationHelper::validateFieldMap(
            $systemValues,
            [
                'name' => ['required'],
                'code' => ['required'],
            ]
        );
        if (!empty($error)) {
            return $error;
        }

        $payload = [
            'systemDefinedFieldsValues' => $systemValues,
            'tagIds'                    => BitCrmHelper::toIntArray($fields['tag_ids'] ?? []),
        ];

        $result = (new \BitApps\CrmPro\Services\ProductService())->store($payload);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function updateProduct()
    {
        if (!BitCrmHelper::isProActive()) {
            return ['status_code' => 400, 'payload' => [], 'response' => __('Bit CRM Pro (Product module) is not installed or activated', 'bit-pi')];
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['product_id' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        $systemValues = $this->buildFieldValues(
            [
                'type'   => $fields['type'] ?? null,
                'brand'  => $fields['brand'] ?? null,
                'status' => $fields['status'] ?? null,
            ]
        );

        $productId = (int) $fields['product_id'];
        $payload = array_merge(['id' => $productId], $systemValues);

        $product = Product::findOne(['id' => $productId, 'is_trash' => 0]);
        if (empty($product)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('Product not found.', 'bit-pi')];
        }

        /*
         * Bit CRM's product update request builds its unique-code rule from a
         * Request instance, so the product cannot be excluded from that check
         * when the payload is a plain array. Write the product here instead and
         * run the same uniqueness check ourselves.
         */
        if (!empty($systemValues['code']) && $systemValues['code'] !== $product->code) {
            $duplicate = Product::findOne(['code' => $systemValues['code'], 'is_trash' => 0]);

            if (!empty($duplicate) && (int) $duplicate->id !== $productId) {
                return ['status_code' => 400, 'payload' => $payload, 'response' => __('Product Code/SKU must be unique!', 'bit-pi')];
            }
        }

        $systemValues['updated_by'] = get_current_user_id();

        if (!$product->update($systemValues)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('Failed to update product.', 'bit-pi')];
        }

        Hooks::doAction('bit_crm/product_updated', $product);

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData($product)];
    }

    public function deleteProduct()
    {
        if (!BitCrmHelper::isProActive()) {
            return ['status_code' => 400, 'payload' => [], 'response' => __('Bit CRM Pro (Product module) is not installed or activated', 'bit-pi')];
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['product_id' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        $payload = ['ids' => [(int) $fields['product_id']]];

        $result = (new \BitApps\CrmPro\Services\ProductService())->trash($payload);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function getAllProducts()
    {
        if (!BitCrmHelper::isProActive()) {
            return ['status_code' => 400, 'payload' => [], 'response' => __('Bit CRM Pro (Product module) is not installed or activated', 'bit-pi')];
        }

        $products = Product::where('is_trash', 0)->get();

        return ['status_code' => 200, 'payload' => [], 'response' => $products ? $products->toArray() : []];
    }

    public function getProductById()
    {
        if (!BitCrmHelper::isProActive()) {
            return ['status_code' => 400, 'payload' => [], 'response' => __('Bit CRM Pro (Product module) is not installed or activated', 'bit-pi')];
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['product_id' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        $payload = ['id' => (int) $fields['product_id']];

        $result = (new \BitApps\CrmPro\Services\ProductService())->show($payload);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function getProductByCode()
    {
        if (!BitCrmHelper::isProActive()) {
            return ['status_code' => 400, 'payload' => [], 'response' => __('Bit CRM Pro (Product module) is not installed or activated', 'bit-pi')];
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['code' => ['required', 'string']]);
        if (!empty($error)) {
            return $error;
        }

        $code = $fields['code'];
        $payload = ['code' => $code];

        $product = BitCrmHelper::normalizeData(Product::findOne(['code' => $code, 'is_trash' => 0]));

        if (empty($product['id'])) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('No product found with this code.', 'bit-pi')];
        }

        $result = (new \BitApps\CrmPro\Services\ProductService())->show(['id' => (int) $product['id']]);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function addTagToProduct()
    {
        if (!BitCrmHelper::isProActive()) {
            return ['status_code' => 400, 'payload' => [], 'response' => __('Bit CRM Pro (Product module) is not installed or activated', 'bit-pi')];
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['product_id' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        if (empty($fields['tag_ids']) && empty($fields['new_tags'])) {
            return ['status_code' => 400, 'payload' => [], 'response' => __('At least one existing or new tag must be provided.', 'bit-pi')];
        }

        $productId = (int) $fields['product_id'];
        $tagIds = BitCrmHelper::toIntArray($fields['tag_ids']);
        $newTags = array_values(array_filter((array) ($fields['new_tags'] ?? [])));

        $attached = (new \BitApps\CrmPro\Services\ProductService())->storeAndAttachTags($productId, $tagIds, $newTags);

        $payload = ['product_id' => $productId, 'tag_ids' => $tagIds];

        if (!empty($attached)) {
            Hooks::doAction('bit_crm/tags_attached_to_products', $attached, [$productId]);
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(Product::findOne(['id' => $productId]))];
    }

    public function removeTagFromProduct()
    {
        if (!BitCrmHelper::isProActive()) {
            return ['status_code' => 400, 'payload' => [], 'response' => __('Bit CRM Pro (Product module) is not installed or activated', 'bit-pi')];
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['product_id' => ['required'], 'tag_ids' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        $productId = (int) $fields['product_id'];
        $tagIds = BitCrmHelper::toIntArray($fields['tag_ids']);

        $detached = (new \BitApps\CrmPro\Services\ProductService())->detachTags($productId, $tagIds);

        $payload = ['product_id' => $productId, 'tag_ids' => $tagIds];

        if (!$detached) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('No matching tags were removed from the product.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(Product::findOne(['id' => $productId]))];
    }
}
