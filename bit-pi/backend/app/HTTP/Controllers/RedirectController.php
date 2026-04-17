<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use WP_Error;

final class RedirectController
{
    /**
     * Handles OAuth callback redirect.
     *
     * This proxy route is used to redirect to the original state URL after OAuth authentication.
     * This is necessary because some platforms do not allow hash-based redirect_url.
     *
     * @param Request $request The incoming request object
     *
     * @return void|WP_Error Returns WP_Error on invalid state URL
     */
    public function handleCallback(Request $request)
    {
        $validatedData = $request->validate(
            [
                'state' => ['required', 'string', 'sanitize:text'],
            ]
        );

        $state = $validatedData['state'];

        $params = $request->queryParams();

        unset($params['rest_route'], $params['state']);

        if (\is_array($params) && $params !== []) {
            $separator = str_contains($state, '?') ? '&' : '?';
            $state .= $separator . http_build_query($params);
        }

        if (wp_safe_redirect($state)) {
            exit;
        }
    }
}
