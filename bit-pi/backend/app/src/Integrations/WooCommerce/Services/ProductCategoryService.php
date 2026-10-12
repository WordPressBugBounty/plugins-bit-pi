<?php

namespace BitApps\Pi\src\Integrations\WooCommerce\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class ProductCategoryService extends BaseService
{
    public function createProductCategory()
    {
        return $this->createTerm('product_cat');
    }

    public function updateProductCategory()
    {
        return $this->updateTerm('product_cat');
    }

    public function deleteProductCategory()
    {
        return $this->deleteTerm('product_cat');
    }

    public function getAllProductCategories()
    {
        return $this->getAllTerms('product_cat');
    }

    public function getProductCategory()
    {
        return $this->getTerm('product_cat');
    }
}
