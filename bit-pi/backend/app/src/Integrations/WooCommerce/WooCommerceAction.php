<?php

namespace BitApps\Pi\src\Integrations\WooCommerce;

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!defined('ABSPATH')) {
    exit;
}


class WooCommerceAction implements ActionInterface
{
    private $nodeInfoProvider;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeWooCommerceAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'] ?? 200,
            $executedNodeAction['payload'] ?? [],
            $executedNodeAction['response'] ?? []
        );
    }

    private function executeWooCommerceAction()
    {
        if ($error = WooCommerceActionHelper::ensureWooCommerceActive()) {
            return $error;
        }

        $machineSlug = $this->nodeInfoProvider->getMachineSlug();

        $services = new WooCommerceServices($this->nodeInfoProvider);

        switch ($machineSlug) {
            case 'createOrder':
                return $services->createOrder();

                break;

            case 'updateOrderStatus':
                return $services->updateOrderStatus();

                break;

            case 'addOrUpdateOrderMeta':
                return $services->addOrUpdateOrderMeta();

                break;

            case 'getTotalOrdersCount':
                return $services->getTotalOrdersCount();

                break;

            case 'getRefundedOrders':
                return $services->getRefundedOrders();

                break;

            case 'getAllOrders':
                return $services->getAllOrders();

                break;

            case 'getOrderByStatus':
                return $services->getOrderByStatus();

                break;

            case 'getOrderByBillingEmail':
                return $services->getOrderByBillingEmail();

                break;

            case 'getOrderByCustomerId':
                return $services->getOrderByCustomerId();

                break;

            case 'getOrderById':
                return $services->getOrderById();

                break;

            case 'getCustomerTotalSpent':
                return $services->getCustomerTotalSpent();

                break;

            case 'getCustomerLastOrder':
                return $services->getCustomerLastOrder();

                break;

            case 'addOrderNote':
                return $services->addOrderNote();

                break;

            case 'getAllCustomers':
                return $services->getAllCustomers();

                break;

            case 'getCustomerById':
                return $services->getCustomerById();

                break;

            case 'getCustomerByEmail':
                return $services->getCustomerByEmail();

                break;

            case 'createNewCustomer':
                return $services->createNewCustomer();

                break;

            case 'createSimpleProduct':
                return $services->createSimpleProduct();

                break;

            case 'createProductVariation':
                return $services->createProductVariation();

                break;

            case 'updateExistingProduct':
                return $services->updateExistingProduct();

                break;

            case 'getAllProducts':
                return $services->getProducts();

                break;

            case 'getAllProductsByCategory':
                return $services->getAllProductsByCategory();

                break;

            case 'getAllSimpleProducts':
                return $services->getProducts('simple');

                break;

            case 'getAllVariableProducts':
                return $services->getProducts('variable');

                break;

            case 'getAllGroupedProducts':
                return $services->getProducts('grouped');

                break;

            case 'getAllExternalProducts':
                return $services->getProducts('external');

                break;

            case 'getAllVariationProducts':
                return $services->getProducts('variation');

                break;

            case 'getAllSubscriptionProducts':
                return $services->getProducts(['subscription', 'variable-subscription']);

                break;

            case 'getProductById':
                return $services->getProductById();

                break;

            case 'getProductBySku':
                return $services->getProductBySku();

                break;

            case 'updateProductStock':
                return $services->updateProductStock();

                break;

            case 'deleteProductPermanently':
                return $services->deleteProduct(true);

                break;

            case 'deleteProductSoftDelete':
                return $services->deleteProduct(false);

                break;

            case 'getProductsTotals':
                return $services->getProductsTotals();

                break;

            case 'getProductSalesCountById':
                return $services->getProductSalesCountById();

                break;

            case 'updateProductStatus':
                return $services->updateProductStatus();

                break;

            case 'createProductCategory':
                return $services->createTermByTax('product_cat');

                break;

            case 'updateProductCategory':
                return $services->updateTermByTax('product_cat');

                break;

            case 'deleteProductCategory':
                return $services->deleteTermByTax('product_cat');

                break;

            case 'getAllProductCategories':
                return $services->getAllTerm('product_cat');

                break;

            case 'getProductCategory':
                return $services->getTermById('product_cat');

                break;

            case 'createProductTag':
                return $services->createTermByTax('product_tag');

                break;

            case 'updateProductTag':
                return $services->updateTermByTax('product_tag');

                break;

            case 'deleteProductTag':
                return $services->deleteTermByTax('product_tag');

                break;

            case 'getAllProductTags':
                return $services->getAllTerm('product_tag');

                break;

            case 'getProductTag':
                return $services->getTermById('product_tag');

                break;

            case 'createProductType':
                return $services->createTermByTax('product_type');

                break;

            case 'updateProductType':
                return $services->updateTermByTax('product_type');

                break;

            case 'deleteProductType':
                return $services->deleteTermByTax('product_type');

                break;

            case 'getAllProductTypes':
                return $services->getAllTerm('product_type');

                break;

            case 'getProductType':
                return $services->getTermById('product_type');

                break;

            case 'createProductBrand':
                return $services->createTermByTax('product_brand');

                break;

            case 'updateProductBrand':
                return $services->updateTermByTax('product_brand');

                break;

            case 'deleteProductBrand':
                return $services->deleteTermByTax('product_brand');

                break;

            case 'getAllProductBrands':
                return $services->getAllTerm('product_brand');

                break;

            case 'getProductBrand':
                return $services->getTermById('product_brand');

                break;

            case 'createProductShippingClass':
                return $services->createTermByTax('product_shipping_class');

                break;

            case 'updateProductShippingClass':
                return $services->updateTermByTax('product_shipping_class');

                break;

            case 'deleteProductShippingClass':
                return $services->deleteTermByTax('product_shipping_class');

                break;

            case 'getAllProductShippingClass':
                return $services->getAllTerm('product_shipping_class');

                break;

            case 'getProductShippingClass':
                return $services->getTermById('product_shipping_class');

                break;

            case 'addProductAttribute':
                return $services->addProductAttribute();

                break;

            case 'removeProductAttribute':
                return $services->removeProductAttribute();

                break;

            case 'createAttribute':
                return $services->createAttribute();

                break;

            case 'getAttribute':
                return $services->getAttribute();

                break;

            case 'updateAttribute':
                return $services->updateAttribute();

                break;

            case 'deleteAttribute':
                return $services->deleteAttribute();

                break;

            case 'getAllCartItems':
                return $services->getAllCartItems();

                break;

            case 'getCartTotals':
                return $services->getCartTotals();

                break;

            case 'addProductToCart':
                return $services->addProductToCart();

                break;

            case 'removeProductFromCart':
                return $services->removeProductFromCart();

                break;

            case 'createCoupon':
                return $services->createCoupon();

                break;

            case 'updateCoupon':
                return $services->updateCoupon();

                break;

            case 'updateCouponCode':
                return $services->updateCouponCode();

                break;

            case 'addEmailsToCoupon':
                return $services->addEmailsToCoupon();

                break;

            case 'deleteCoupon':
                return $services->deleteCoupon();

                break;

            case 'getAllCoupon':
                return $services->getAllCoupon();

                break;

            case 'getCouponById':
                return $services->getCouponById();

                break;

            case 'getCouponTotals':
                return $services->getCouponTotals();

                break;

            case 'applyCouponToCart':
                return $services->applyCouponToCart();

                break;

            case 'getAppliedCoupons':
                return $services->getAppliedCoupons();

                break;

            case 'removeCouponFromCart':
                return $services->removeCouponFromCart();

                break;

            case 'getAllReviews':
                return $services->getAllReviews();

                break;

            case 'topSellingProductsReport':
                return $services->topSellingProductsReport();

                break;
        }
    }
}
