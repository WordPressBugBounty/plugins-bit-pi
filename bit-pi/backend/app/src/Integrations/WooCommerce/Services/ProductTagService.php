<?php

namespace BitApps\Pi\src\Integrations\WooCommerce\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class ProductTagService extends BaseService
{
    public function createProductTag()
    {
        return $this->createTerm('product_tag');
    }

    public function updateProductTag()
    {
        return $this->updateTerm('product_tag');
    }

    public function deleteProductTag()
    {
        return $this->deleteTerm('product_tag');
    }

    public function getAllProductTags()
    {
        return $this->getAllTerms('product_tag');
    }

    public function getProductTag()
    {
        return $this->getTerm('product_tag');
    }
}
