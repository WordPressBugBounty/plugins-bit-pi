<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPValidator\Validator;

class OauthCallbackController
{
    public function handleOauthCallback($template)
    {
        if (get_query_var('pagename') !== Config::SLUG . '-oauth-callback') {
            return $template;
        }

        header('X-Robots-Tag: noindex, nofollow');

        // Nonce verification is not applicable here -- this handles OAuth redirects from external providers
        $validation = (new Validator())->make(
            wp_unslash($_GET), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            [
                'state' => ['required', 'url', 'sanitize:url'],
                '*'     => ['string', 'sanitize:text'],
            ]
        );

        if ($validation->fails()) {
            wp_send_json(
                [
                    'status' => 'error',
                    'code'   => 'VALIDATION',
                    'data'   => $validation->errors(),
                ],
                422
            );
        }

        $params = $validation->validated();

        $state = $params['state'];

        unset($params['rest_route'], $params['state']);

        if (\count($params) > 0) {
            $separator = str_contains($state, '?') ? '&' : '?';
            $state .= $separator . http_build_query($params);
        }

        if (wp_safe_redirect($state)) {
            exit;
        }
    }
}
