<?php

namespace BitApps\Pi\src\Integrations\WooCommerce\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class ProductShippingClassService extends BaseService
{
    public function createProductShippingClass()
    {
        return $this->createTerm('product_shipping_class');
    }

    public function updateProductShippingClass()
    {
        return $this->updateTerm('product_shipping_class');
    }

    public function deleteProductShippingClass()
    {
        return $this->deleteTerm('product_shipping_class');
    }

    public function getAllProductShippingClass()
    {
        return $this->getAllTerms('product_shipping_class');
    }

    public function getProductShippingClass()
    {
        return $this->getTerm('product_shipping_class');
    }
}
