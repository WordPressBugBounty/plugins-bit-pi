<?php

namespace BitApps\Pi\src\Integrations\WooCommerce\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class ProductBrandService extends BaseService
{
    public function createProductBrand()
    {
        return $this->createTerm('product_brand');
    }

    public function updateProductBrand()
    {
        return $this->updateTerm('product_brand');
    }

    public function deleteProductBrand()
    {
        return $this->deleteTerm('product_brand');
    }

    public function getAllProductBrands()
    {
        return $this->getAllTerms('product_brand');
    }

    public function getProductBrand()
    {
        return $this->getTerm('product_brand');
    }
}
