<?php

namespace BitApps\Pi\src\Integrations\WooCommerce\Services;

use BitApps\Pi\src\Integrations\IntegrationHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class CartService extends BaseService
{
    public function getAllCartItems()
    {
        $cartItems = array_map(
            function ($item) {
                $item['data'] = $item['data']->get_data();

                return $item;
            },
            WC()->cart->get_cart()
        );

        return [
            'response'    => $cartItems,
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    public function getCartTotals()
    {
        return [
            'response'    => WC()->cart->get_totals(),
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    public function addProductToCart()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'product_id'       => ['required'],
            'product_quantity' => ['required']
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        WC()->cart->add_to_cart($fieldMappings['product_id'], $fieldMappings['product_quantity']);

        return [
            'response'    => WC()->cart->get_cart(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function removeProductFromCart()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'product_id' => ['required', 'integer']
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $productCartId = WC()->cart->generate_cart_id($fieldMappings['product_id']);
        $cartItemKey = WC()->cart->find_product_in_cart($productCartId);

        if (empty($cartItemKey)) {
            return [
                'response'    => __('Product not found in cart with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 400,
            ];
        }

        WC()->cart->remove_cart_item($cartItemKey);

        if (!empty($fieldMappings['message'])) {
            wc_add_notice($fieldMappings['message']);
        }

        return [
            'response'    => WC()->cart->get_cart(),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function sendAbandonedCartEmail(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = ['email' => ['required', 'email']];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $subject = !empty($fieldMappings['email_subject'])
            ? $fieldMappings['email_subject']
            : __('You left something behind!', 'bit-pi');

        $body = !empty($fieldMappings['email_body'])
            ? $fieldMappings['email_body']
            : \sprintf(
                // translators: %s: cart page URL
                __('You left items in your cart. <a href="%s">Return to your cart</a> to complete your purchase!', 'bit-pi'),
                esc_url(wc_get_cart_url())
            );

        $mailError = '';
        $captureMailError = function ($error) use (&$mailError) {
            $mailError = $error->get_error_message();
        };

        add_action('wp_mail_failed', $captureMailError);
        $sent = wp_mail($fieldMappings['email'], $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
        remove_action('wp_mail_failed', $captureMailError);

        if (!$sent) {
            return [
                'response' => $mailError
                    // translators: %s: mailer error message
                    ? \sprintf(__('Failed to send abandoned cart recovery email: %s', 'bit-pi'), $mailError)
                    : __('Failed to send abandoned cart recovery email.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 500,
            ];
        }

        return [
            'response'    => __('Abandoned cart recovery email sent successfully.', 'bit-pi'),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function checkCartStatus(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = ['email' => ['required', 'email']];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $user = get_user_by('email', $fieldMappings['email']);

        $orders = wc_get_orders(
            [
                'billing_email' => $fieldMappings['email'],
                'limit'         => 5,
                'orderby'       => 'date',
                'order'         => 'DESC',
            ]
        );

        $hasRecentOrder = !empty($orders);
        $mostRecentOrder = $hasRecentOrder ? reset($orders) : null;
        $mostRecentStatus = $mostRecentOrder ? $mostRecentOrder->get_status() : null;

        $cartStatus = 'unknown';

        if ($hasRecentOrder && \in_array($mostRecentStatus, ['completed', 'processing', 'on-hold'])) {
            $cartStatus = 'recovered';
        } elseif ($hasRecentOrder && \in_array($mostRecentStatus, ['pending', 'failed', 'cancelled'])) {
            $cartStatus = 'abandoned';
        }

        $response = [
            'email'               => $fieldMappings['email'],
            'customer_id'         => $user ? $user->ID : 0,
            'cart_status'         => $cartStatus,
            'recent_order_status' => $mostRecentStatus,
            'recent_order_id'     => $mostRecentOrder ? $mostRecentOrder->get_id() : null,
        ];

        return [
            'response'    => $response,
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }
}
