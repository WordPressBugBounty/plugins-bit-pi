<?php

namespace BitApps\Pi\Providers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Hooks\Hooks;

class RewriteRuleProvider
{
    private string $route;

    private string $pagename;

    private array $queryVars;

    private array $rewriteRules;

    private bool $shouldFlush;

    public function __construct(string $route, bool $shouldFlush = false)
    {
        $this->route = $route;
        $this->shouldFlush = $shouldFlush;
        $this->makeRewriteRule();
        Hooks::addAction('init', [$this, 'rewriteUrl']);
        Hooks::addAction('query_vars', [$this, 'addQueryVars']);
        Hooks::addFilter('pre_handle_404', [$this, 'preventNotFound']);
        Hooks::addFilter('redirect_canonical', [$this, 'preventCanonicalRedirect']);
    }

    /**
     * The route maps to a pagename with no real page behind it, so the main query is empty and
     * WordPress would mark the request 404. That runs before our template_include handler, and
     * "redirect broken URLs" features (SEO plugins, redirect managers) act on is_404() at
     * template_redirect and send the request elsewhere. Short-circuit the 404 for our route.
     *
     * @param bool $preempt
     *
     * @return bool
     */
    public function preventNotFound($preempt)
    {
        return $preempt || $this->isCurrentRoute();
    }

    /**
     * Once the request is no longer a 404, WordPress would 301 the slash-less form of the route
     * (e.g. the advertised MCP authorization endpoint) to the trailing-slash form. Serve it directly,
     * as it was before the 404 short-circuit.
     *
     * @param false|string $redirectUrl
     *
     * @return false|string
     */
    public function preventCanonicalRedirect($redirectUrl)
    {
        return $this->isCurrentRoute() ? false : $redirectUrl;
    }

    public function rewriteUrl()
    {
        if (!isset($this->rewriteRules)) {
            return;
        }

        foreach ($this->rewriteRules as $regex => $query) {
            add_rewrite_rule($regex, $query, 'top');
        }
        // Flush rewrite rules if needed
        if ($this->shouldFlush) {
            flush_rewrite_rules();
        }
    }

    public function makeRewriteRule()
    {
        // Extract parameters from route
        preg_match_all('/\{(\w+)\}/', $this->route, $matches);
        $this->queryVars = $matches[1] ?? [];

        // Create route path and pagename
        $path = trim($this->route, '/');
        $this->pagename = str_replace(['/', '{', '}'], ['-', '', ''], $path);

        // Build regex and query string
        $regex = '^' . preg_replace('/\{(\w+)\}/', '([^/]+)', $path) . '/?$';
        $query = "index.php?pagename={$this->pagename}";

        // Add parameters to query
        foreach ($this->queryVars as $i => $param) {
            $query .= "&{$param}=\$matches[" . ($i + 1) . ']';
        }

        $this->queryVars[] = 'pagename';

        $this->rewriteRules = [$regex => $query];
    }

    public function addQueryVars($vars)
    {
        if (!isset($this->rewriteRules)) {
            return;
        }

        return array_merge($vars, $this->queryVars);
    }

    private function isCurrentRoute(): bool
    {
        return get_query_var('pagename') === $this->pagename;
    }
}
