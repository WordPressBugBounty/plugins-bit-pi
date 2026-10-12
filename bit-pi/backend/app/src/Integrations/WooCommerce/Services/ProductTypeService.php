<?php

namespace BitApps\Pi\src\Integrations\WooCommerce\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class ProductTypeService extends BaseService
{
    public function createProductType()
    {
        return $this->createTerm('product_type');
    }

    public function updateProductType()
    {
        return $this->updateTerm('product_type');
    }

    public function deleteProductType()
    {
        return $this->deleteTerm('product_type');
    }

    public function getAllProductTypes()
    {
        return $this->getAllTerms('product_type');
    }

    public function getProductType()
    {
        return $this->getTerm('product_type');
    }
}
