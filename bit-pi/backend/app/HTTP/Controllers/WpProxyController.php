<?php

namespace BitApps\Pi\HTTP\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\Factories\ProxyRequestParserFactory;
use BitApps\Pi\HTTP\Requests\WpProxyRequest;

/**
 * Reads this site's own REST API on behalf of the flow builder.
 *
 * A few integrations are WordPress plugins living on this very site, so their
 * lists and fields come from the local REST API, reached with the application
 * password the admin entered. The general proxy refuses every internal address
 * to stop it being pointed at the network behind the server, which is exactly
 * what those calls look like — so they come here instead, where the client
 * sends a REST route and never a host.
 */
final class WpProxyController
{
    /**
     * Make a request against this site's REST API.
     *
     * @return Response
     */
    public function proxyRequest(WpProxyRequest $request)
    {
        $validated = ProxyRequestParserFactory::parse($request->validated());

        $path = ltrim($validated['path'], '/');

        if ($this->isInvalidPath($path)) {
            return Response::error('A REST route is required.');
        }

        $method = strtoupper($validated['method']);
        $headers = $validated['headers'] ?? null;
        $queryParams = $validated['queryParams'] ?? null;
        $bodyParams = $method === 'POST' ? $validated['bodyParams'] ?? null : null;

        // The host is the site's own, whatever the client sent
        $url = get_rest_url(null, $path);

        if (!\is_null($queryParams)) {
            // Plain permalinks put the route in a query of its own, as
            // ?rest_route=/..., so the separator depends on the URL
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($queryParams);
        }

        $response = (new HttpClient())->request($url, $method, $bodyParams, $headers);

        if (is_wp_error($response)) {
            return Response::error('Something went wrong');
        }

        return Response::success($response);
    }

    /**
     * Whether a path is something other than a REST route.
     *
     * A route, not a URL: anything carrying a scheme or climbing out of the
     * REST base is a client trying to choose the destination itself, which is
     * the one thing this endpoint exists to take away from it.
     *
     * @param string $path
     *
     * @return bool
     */
    private function isInvalidPath($path)
    {
        return $path === '' || strpos($path, '..') !== false || strpos($path, '://') !== false;
    }
}
