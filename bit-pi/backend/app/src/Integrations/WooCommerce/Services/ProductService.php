<?php

namespace BitApps\Pi\src\Integrations\WooCommerce\Services;

use BitApps\Pi\src\Integrations\IntegrationHelper;
use WC_Product;
use WC_Product_Variation;

if (!defined('ABSPATH')) {
    exit;
}

final class ProductService extends BaseService
{
    public function createSimpleProduct()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'name'          => ['required'],
            'regular_price' => ['required'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
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
        $product = $this->updateProductProperties($fieldMappings, $product, $propsKeys);

        $this->updateProductMetadata($fieldMappings, $postId);
        $this->setProductTerms($fieldMappings, $postId);

        if (isset($fieldMappings['featured_image'])) {
            $attachmentId = $this->uploadProductImage(
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
                $attachmentId = $this->uploadProductImage(
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

        wc_delete_product_transients($postId);
        wc_update_product_lookup_tables($product->get_id());

        return [
            'response'    => $product->get_data(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function createProductVariation()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();
        $fieldMappings['attributes'] = $this->nodeInfoProvider->getFieldMapRepeaters('attribute-field-mapping.value', false, true, 'attribute_key', 'value');

        $validationRules = [
            'product_id'    => ['required'],
            'regular_price' => ['required'],
            'sku'           => ['required'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $variation = new WC_Product_Variation();
        $variation->set_parent_id((int) $fieldMappings['product_id']);
        $variation->set_attributes($fieldMappings['attributes']);

        if (isset($fieldMappings['variation_image'])) {
            $attachmentId = $this->getVariationAttachmentId(
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

        $variation = $this->updateProductProperties($fieldMappings, $variation, $propsKeys);

        return [
            'response'    => $variation->get_data(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function updateExistingProduct()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'product_id' => ['required', 'integer'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
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

        $product = $this->updateProductProperties($fieldMappings, $product, $propsKeys);

        $this->updateProductMetadata($fieldMappings, $productId);
        $this->setProductTerms($fieldMappings, $productId);

        if (isset($fieldMappings['featured_image'])) {
            $attachmentId = $this->uploadProductImage(
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
                $attachmentId = $this->uploadProductImage(
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

    public function getAllProducts()
    {
        return $this->listProducts();
    }

    public function getAllSimpleProducts()
    {
        return $this->listProducts('simple');
    }

    public function getAllVariableProducts()
    {
        return $this->listProducts('variable');
    }

    public function getAllGroupedProducts()
    {
        return $this->listProducts('grouped');
    }

    public function getAllExternalProducts()
    {
        return $this->listProducts('external');
    }

    public function getAllVariationProducts()
    {
        return $this->listProducts('variation');
    }

    public function getAllSubscriptionProducts()
    {
        return $this->listProducts(['subscription', 'variable-subscription']);
    }

    public function deleteProductPermanently()
    {
        return $this->deleteProduct(true);
    }

    public function deleteProductSoftDelete()
    {
        return $this->deleteProduct(false);
    }

    public function getAllProductsByCategory(): array
    {
        $categorySlug = $this->nodeInfoProvider->getFieldMapConfigs('product-category.value') ?? null;
        $payload = ['category' => $categorySlug];

        $validationRules = [
            'category' => ['required']
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($payload, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $products = $this->getProductsData(null, $categorySlug);

        return [
            'response'    => $products,
            'payload'     => $payload,
            'status_code' => 200,
        ];
    }

    public function getProductById(): array
    {
        $productId = $this->nodeInfoProvider->getFieldMapData()['id'] ?? null;
        $payload = ['product_id' => $productId];

        $validationRules = [
            'product_id' => ['required', 'integer']
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($payload, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $product = wc_get_product($productId);

        if (empty($product)) {
            return [
                'response'    => __('Product not found with the given ID.', 'bit-pi'),
                'payload'     => $payload,
                'status_code' => 404,
            ];
        }

        return [
            'response'    => $product->get_data(),
            'payload'     => $payload,
            'status_code' => 200,
        ];
    }

    public function getProductBySku(): array
    {
        $productSku = $this->nodeInfoProvider->getFieldMapData()['sku'] ?? null;
        $payload = ['product_sku' => $productSku];

        $validationRules = [
            'product_sku' => ['required']
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($payload, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $productId = wc_get_product_id_by_sku($productSku);

        if (empty($productId)) {
            return [
                'response'    => __('Product not found with the given SKU.', 'bit-pi'),
                'payload'     => $payload,
                'status_code' => 404,
            ];
        }

        return [
            'response'    => wc_get_product($productId)->get_data(),
            'payload'     => $payload,
            'status_code' => 200,
        ];
    }

    public function updateProductStock(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'id'        => ['required'],
            'stock'     => ['required'],
            'operation' => ['required']
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
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
            $products = $this->getProductsData($slug);

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

    public function getProductSalesCountById(): array
    {
        $productId = $this->nodeInfoProvider->getFieldMapData()['id'] ?? null;
        $payload = ['product_id' => $productId];

        $validationRules = [
            'product_id' => ['required',  'integer']
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($payload, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $product = wc_get_product($productId);

        if (empty($product)) {
            return [
                'response'    => __('Product not found with the given ID.', 'bit-pi'),
                'payload'     => $payload,
                'status_code' => 404,
            ];
        }

        $report = $this->getProductOrderStatusCount($product);

        return [
            'response'    => $report,
            'payload'     => $payload,
            'status_code' => 200,
        ];
    }

    public function updateProductStatus(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'id'     => ['required'],
            'status' => ['required']
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

    public function getProductByNameOrSlug(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = ['name_or_slug' => ['required', 'string']];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $products = wc_get_products(
            [
                'limit'  => -1,
                'status' => 'any',
                's'      => $fieldMappings['name_or_slug'],
            ]
        );

        if (empty($products)) {
            $post = get_page_by_path($fieldMappings['name_or_slug'], OBJECT, 'product');

            if ($post) {
                $products = [wc_get_product($post->ID)];
            }
        }

        if (empty($products)) {
            return [
                'response'    => __('No product found with the given name or slug.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $productsData = array_map(
            function ($product) {
                return $product->get_data();
            },
            array_filter($products)
        );

        return [
            'response'    => array_values($productsData),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function getProductVariation(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'product_id'   => ['required', 'integer'],
            'variation_id' => ['required', 'integer'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $variation = wc_get_product((int) $fieldMappings['variation_id']);

        if (!$variation instanceof WC_Product_Variation || (int) $variation->get_parent_id() !== (int) $fieldMappings['product_id']) {
            return [
                'response'    => __('Product variation not found.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        return [
            'response'    => $variation->get_data(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function updateProductVariation(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();
        $fieldMappings['attributes'] = $this->nodeInfoProvider->getFieldMapRepeaters('attribute-field-mapping.value', false, true, 'attribute_key', 'value');

        $validationRules = [
            'product_id'   => ['required', 'integer'],
            'variation_id' => ['required', 'integer'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $variation = wc_get_product((int) $fieldMappings['variation_id']);

        if (!$variation instanceof WC_Product_Variation || (int) $variation->get_parent_id() !== (int) $fieldMappings['product_id']) {
            return [
                'response'    => __('Product variation not found.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        if (!empty($fieldMappings['attributes'])) {
            $variation->set_attributes($fieldMappings['attributes']);
        }

        if (isset($fieldMappings['variation_image'])) {
            $attachmentId = $this->getVariationAttachmentId(
                $fieldMappings['variation_image'],
                $variation->get_id()
            );

            if ($attachmentId) {
                $variation->set_image_id($attachmentId);
            }
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
            'weight',
            'length',
            'width',
            'height',
        ];

        $variation = $this->updateProductProperties($fieldMappings, $variation, $propsKeys);

        return [
            'response'    => $variation->get_data(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function updateProductPrice(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = ['product_id_or_sku' => ['required']];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $findBy = $this->nodeInfoProvider->getFieldMapConfigs('find-product-by.value');
        $product = $this->findProductByIdOrSku((string) $fieldMappings['product_id_or_sku'], $findBy);

        if (empty($product)) {
            return [
                'response'    => __('Product not found with the given ID or SKU.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        if ($this->nodeInfoProvider->getFieldMapConfigs('remove-sale-price.value')) {
            $fieldMappings['sale_price'] = '';
        }

        $propsKeys = ['regular_price', 'sale_price', 'price'];

        $product = $this->updateProductProperties($fieldMappings, $product, $propsKeys);

        wc_delete_product_transients($product->get_id());

        return [
            'response'    => $product->get_data(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    private function findProductByIdOrSku($idOrSku, $findBy)
    {
        $productById = $findBy !== 'sku' && is_numeric($idOrSku) ? wc_get_product((int) $idOrSku) : null;

        if ($productById || $findBy === 'id') {
            return $productById;
        }

        $productId = wc_get_product_id_by_sku($idOrSku);

        return $productId ? wc_get_product($productId) : null;
    }

    private function listProducts($type = null): array
    {
        $products = $this->getProductsData($type);

        return [
            'response'    => $products,
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    private function deleteProduct($force): array
    {
        $productId = $this->nodeInfoProvider->getFieldMapData()['id'] ?? null;
        $payload = ['product_id' => $productId];

        $validationRules = [
            'product_id' => ['required', 'integer']
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($payload, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
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

    private function getProductsData($type = null, $category = null): array
    {
        $args = [
            'limit'  => -1,
            'return' => 'objects',
        ];

        if (isset($type)) {
            $args['type'] = $type;
        }

        if (isset($category)) {
            $args['category'] = $category;
        }

        return array_map(
            function ($product) {
                return $product->get_data();
            },
            wc_get_products($args)
        );
    }

    private function getProductOrderStatusCount($product)
    {
        if (!$product instanceof WC_Product) {
            return [];
        }

        $productId = $product->get_id();

        $statuses = [
            'wc-pending'    => 'total_orders_on_pending',
            'wc-processing' => 'total_orders_on_processing',
            'wc-on-hold'    => 'total_orders_on_hold',
            'wc-cancelled'  => 'total_orders_cancelled',
            'wc-completed'  => 'total_orders_completed',
            'wc-refunded'   => 'total_orders_refunded',
            'wc-failed'     => 'total_orders_failed',
        ];

        $report = array_fill_keys($statuses, 0);

        $all_orders = wc_get_orders(
            [
                'status' => array_keys($statuses),
                'limit'  => -1,
                'return' => 'objects',
            ]
        );

        foreach ($all_orders as $order) {
            $status = $order->get_status();

            if (!isset($statuses["wc-{$status}"])) {
                continue;
            }

            foreach ($order->get_items() as $item) {
                if ((int) $item->get_product_id() === (int) $productId) {
                    ++$report[$statuses["wc-{$status}"]];

                    break;
                }
            }
        }

        return $report;
    }

    private function updateProductMetadata($fieldMappings, $postId)
    {
        $metaKeysMapping = [
            '_visibility'        => 'catalog_visibility',
            '_stock_status'      => 'stock_status',
            'catalog_visibility' => 'catalog_visibility',
            '_sku'               => 'sku',
            '_manage_stock'      => 'manage_stock',
            '_stock'             => 'stock_quantity',
            '_product_url'       => 'external_url',
            '_sold_individually' => 'sold_individually',
        ];

        foreach ($metaKeysMapping as $metaKey => $fieldKey) {
            if (isset($fieldMappings[$fieldKey])) {
                update_post_meta($postId, $metaKey, $fieldMappings[$fieldKey]);
            }
        }
    }

    private function updateProductProperties($fieldMappings, $product, $propsKeys = [])
    {
        if (empty($fieldMappings['virtual'])) {
            $propsKeys = array_merge($propsKeys, ['weight', 'length', 'width', 'height']);
        }

        if ($fieldMappings['sale_schedule'] ?? false) {
            $propsKeys = array_merge($propsKeys, ['date_on_sale_from', 'date_on_sale_to']);
        }

        if ($fieldMappings['manage_stock'] ?? false) {
            $propsKeys = array_merge($propsKeys, ['stock_quantity', 'low_stock_amount', 'stock_status', 'backorders']);
        }

        $props = [];

        foreach ($propsKeys as $key) {
            if ($key === 'price' && isset($fieldMappings['regular_price'])) {
                $props[$key] = wc_format_decimal($fieldMappings['regular_price']);
            } elseif (isset($fieldMappings[$key])) {
                $props[$key] = \in_array($key, ['regular_price', 'sale_price'])
                    ? wc_format_decimal($fieldMappings[$key] ?? 0)
                    : $fieldMappings[$key];
            }
        }

        if (empty($props)) {
            return $product;
        }

        $product->set_props($props);
        $product->save();

        return $product;
    }

    private function setProductTerms($fieldMappings, $productId)
    {
        if (!empty($fieldMappings['category_ids'])) {
            wp_set_object_terms($productId, $fieldMappings['category_ids'], 'product_cat');
        }

        if (!empty($fieldMappings['tag_ids'])) {
            wp_set_object_terms($productId, $fieldMappings['tag_ids'], 'product_tag');
        }

        if (!empty($fieldMappings['brand_ids'])) {
            wp_set_object_terms($productId, $fieldMappings['brand_ids'], 'product_brand');
        }
    }

    private function uploadProductImage($imageUrl, $postId, $fieldMappings)
    {
        $imageRequest = wp_safe_remote_get($imageUrl);

        if (is_wp_error($imageRequest)) {
            return [
                'response'    => $imageRequest->get_error_message(),
                'payload'     => $fieldMappings,
                'status_code' => $imageRequest->get_error_code(),
            ];
        }

        $imageContent = wp_remote_retrieve_body($imageRequest);

        $uploadResult = wp_upload_bits(basename($imageUrl), null, $imageContent);

        if ($uploadResult['error']) {
            return [
                'response'    => __('Image upload failed: ', 'bit-pi') . $uploadResult['error'],
                'payload'     => $fieldMappings,
                'status_code' => 422,
            ];
        }

        $uploadedFilePath = $uploadResult['file'];
        $wpFiletype = wp_check_filetype($uploadedFilePath, null);

        $attachmentData = [
            'post_mime_type' => $wpFiletype['type'],
            'post_title'     => sanitize_file_name($uploadedFilePath),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachmentId = wp_insert_attachment($attachmentData, $uploadedFilePath, $postId);

        if (is_wp_error($attachmentId)) {
            return [
                'response'    => $attachmentId->get_error_message(),
                'payload'     => $fieldMappings,
                'status_code' => $attachmentId->get_error_code(),
            ];
        }

        include_once ABSPATH . 'wp-admin/includes/image.php';

        $attachmentMeta = wp_generate_attachment_metadata($attachmentId, $uploadedFilePath);

        wp_update_attachment_metadata($attachmentId, $attachmentMeta);

        return $attachmentId;
    }

    private function getVariationAttachmentId($variationImage, $variationId)
    {
        if (!\function_exists('media_handle_upload')) {
            include_once ABSPATH . 'wp-admin/includes/media.php';
        }

        if (!\function_exists('wp_handle_upload')) {
            include_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if (!\function_exists('wp_generate_attachment_metadata')) {
            include_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $existingMediaId = attachment_url_to_postid($variationImage);

        if ($existingMediaId) {
            return $existingMediaId;
        }

        $attachmentId = media_sideload_image($variationImage, $variationId, null, 'id');

        if (is_wp_error($attachmentId)) {
            return;
        }

        return $attachmentId;
    }
}
