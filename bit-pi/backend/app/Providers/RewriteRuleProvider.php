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

    private array $queryVars;

    private array $rewriteRules;

    public function __construct(
        string $route
    ) {
        $this->route = $route;
        $this->makeRewriteRule();
        Hooks::addAction('init', [$this, 'rewriteUrl']);
        Hooks::addAction('query_vars', [$this, 'addQueryVars']);
    }

    public function rewriteUrl()
    {
        if (!isset($this->rewriteRules)) {
            return;
        }

        foreach ($this->rewriteRules as $regex => $query) {
            add_rewrite_rule($regex, $query, 'top');
        }

        flush_rewrite_rules();
    }

    public function makeRewriteRule()
    {
        // Extract parameters from route
        preg_match_all('/\{(\w+)\}/', $this->route, $matches);
        $this->queryVars = $matches[1] ?? [];

        // Create route path and pagename
        $path = trim($this->route, '/');
        $pagename = str_replace(['/', '{', '}'], ['-', '', ''], $path);

        // Build regex and query string
        $regex = '^' . preg_replace('/\{(\w+)\}/', '([^/]+)', $path) . '/?$';
        $query = "index.php?pagename={$pagename}";

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
}
