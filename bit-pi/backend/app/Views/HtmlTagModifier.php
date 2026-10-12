<?php

// phpcs:disable Squiz.NamingConventions.ValidVariableName.NotCamelCaps

namespace BitApps\Pi\Views;

use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Hooks\Hooks;

if (!defined('ABSPATH')) {
    exit;
}


final class HtmlTagModifier
{
    public function __construct()
    {
        Hooks::addFilter('style_loader_tag', [$this, 'updateLinkAttributes'], 0, 2);
        Hooks::addFilter('script_loader_tag', [$this, 'updateScriptAttributes'], 0, 1);
        Hooks::addFilter('script_loader_src', [$this, 'removeQueryParam'], 99999, 3);
    }

    public function updateScriptAttributes($html)
    {
        $slug = Config::SLUG;

        $typeAttribute = 'type="module"';

        $keys = [
            '-vite-client-helper-MODULE-js',
            '-vite-client-MODULE-js',
            '-index-MODULE-js',
        ];

        if (Config::getEnv('DEV')) {
            foreach ($keys as $key) {
                $handle = 'id="' . $slug . $key . '"';

                if (str_contains($html, $handle)) {
                    $html = str_replace($handle, $handle . ' ' . $typeAttribute, $html);
                }
            }
        } else {
            $handle = 'id="' . $slug . '-index-MODULE-js"';

            if (str_contains($html, $handle)) {
                $html = str_replace($handle, $handle . ' ' . $typeAttribute, $html);
            }
        }

        return $html;
    }

    public function updateLinkAttributes($html, $handle)
    {
        $slug = Config::SLUG;

        if (!str_contains($handle, $slug)) {
            return $html;
        }

        if (str_contains($handle, 'PRECONNECT')) {
            $html = str_replace("rel='stylesheet'", 'rel="preconnect"', $html);
        }

        if (str_contains($handle, 'PRELOAD')) {
            $html = str_replace("rel='stylesheet'", 'rel="preload"', $html);
        }

        if (str_contains($handle, 'CROSSORIGIN')) {
            $id = "id='{$handle}-css'";
            $html = str_replace($id, $id . ' crossorigin', $html);
        }

        if (str_contains($html, 'SCRIPT')) {
            $id = "id='{$handle}-css'";
            $html = str_replace($id, $id . ' as="script"', $html);
        }

        return $html;
    }

    public function removeQueryParam($src, $handle)
    {
        if (Config::SLUG . '-index-MODULE' === $handle) {
            $src = strtok($src, '?');
        }

        return $src;
    }
}
