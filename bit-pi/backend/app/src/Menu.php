<?php

namespace BitApps\Pi\src;

use BitApps\Pi\Config;
use BitApps\Pi\Views\Body;

if (!defined('ABSPATH')) {
    exit;
}


final class Menu
{
    /**
     * Provides menus for wordpress admin sidebar.
     * should return an array of menus with the following structure:
     * [
     *   'type' => menu | submenu,
     *  'name' => 'Name of menu will shown in sidebar',
     *  'capability' => 'capability required to access menu',
     *  'slug' => 'slug of menu after ?page=',.
     *
     *  'title' => 'page title will be shown in browser title if type is menu',
     *  'callback' => 'function to call when menu is clicked',
     *  'icon' =>   'icon to display in menu if menu type is menu',
     *  'position' => 'position of menu in sidebar if menu type is menu',
     *
     * 'parent' => 'parent slug if submenu'
     * ]
     *
     * @return array
     */
    public static function getSideBarMenu(Body $body)
    {
        return [
            'Home'        => self::getHomeMenuAttributes($body),
            'Dashboard'   => self::getDashboardMenuAttributes(),
            'Flows'       => self::getFlowMenuAttributes(),
            'Connections' => self::getConnectionMenuAttributes(),
            'Webhooks'    => self::getWebhookMenuAttributes(),
            'Custom Apps' => self::getCustomAppsMenuAttributes(),
            'Settings'    => self::getSettingsMenuAttributes(),
            'Support'     => self::getSupportMenuAttributes(),
        ];
    }

    private static function getHomeMenuAttributes($body)
    {
        return [
            'type'       => 'menu',
            'title'      => __("Bit Flows - Your flow of automation's", 'bit-pi'),
            'name'       => __('Bit Flows', 'bit-pi'),
            'capability' => 'manage_options',
            'slug'       => Config::SLUG,
            'callback'   => [$body, 'render'],
            'icon'       => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="20" height="28" fill="#fff" fill-rule="evenodd" xmlns:v="https://vecta.io/nano"><path d="M2.909 9.675l7.664-7.013c1.607-1.47 4.101-1.359 5.571.247s1.359 4.101-.247 5.571l-7.664 7.013c-1.607 1.47-4.101 1.359-5.571-.247s-1.359-4.101.247-5.571zm1.329 1.452c-.804.736-.86 1.985-.124 2.79s1.985.86 2.789.124l7.664-7.013c.804-.736.86-1.985.124-2.789s-1.985-.86-2.789-.124l-7.664 7.013z"/><path d="M7.338 14.516l4.079-3.732c1.607-1.47 4.101-1.359 5.571.247s1.359 4.101-.247 5.571l-4.079 3.732c-1.607 1.47-4.101 1.359-5.571-.247s-1.359-4.101.247-5.571zm1.329 1.452c-.804.736-.86 1.985-.124 2.79s1.985.86 2.789.124l4.079-3.732c.804-.736.86-1.985.124-2.789s-1.985-.86-2.79-.124l-4.079 3.732z"/><path d="M17.091 25.174c-1.607 1.47-4.101 1.359-5.571-.247s-1.359-4.101.247-5.571 4.101-1.359 5.571.247 1.359 4.101-.247 5.571zm-3.995-4.366c-.804.736-.86 1.985-.124 2.789s1.985.86 2.789.124.86-1.985.124-2.789-1.985-.86-2.79-.124z"/></svg>'), // phpcs:ignore Generic.Files.LineLength.MaxExceeded
            'position'   => '20',
        ];
    }

    private static function getDashboardMenuAttributes()
    {
        return [
            'parent'     => Config::SLUG,
            'type'       => 'submenu',
            'name'       => 'Dashboard',
            'capability' => 'manage_options',
            'slug'       => Config::SLUG . '#/',
        ];
    }

    private static function getFlowMenuAttributes()
    {
        return [
            'parent'     => Config::SLUG,
            'type'       => 'submenu',
            'name'       => 'Flows',
            'capability' => 'manage_options',
            'slug'       => Config::SLUG . '#/flows',
        ];
    }

    private static function getConnectionMenuAttributes()
    {
        return [
            'parent'     => Config::SLUG,
            'type'       => 'submenu',
            'name'       => 'Connections',
            'capability' => 'manage_options',
            'slug'       => Config::SLUG . '#/connections',
        ];
    }

    private static function getWebhookMenuAttributes()
    {
        return [
            'parent'     => Config::SLUG,
            'type'       => 'submenu',
            'name'       => 'Webhooks',
            'capability' => 'manage_options',
            'slug'       => Config::SLUG . '#/webhooks',
        ];
    }

    private static function getCustomAppsMenuAttributes()
    {
        return [
            'parent'     => Config::SLUG,
            'type'       => 'submenu',
            'name'       => 'Custom Apps',
            'capability' => 'manage_options',
            'slug'       => Config::SLUG . '#/custom-apps',
        ];
    }

    private static function getSettingsMenuAttributes()
    {
        return [
            'parent'     => Config::SLUG,
            'type'       => 'submenu',
            'name'       => 'Settings',
            'capability' => 'manage_options',
            'slug'       => Config::SLUG . '#/settings',
        ];
    }

    private static function getSupportMenuAttributes()
    {
        return [
            'parent'     => Config::SLUG,
            'type'       => 'submenu',
            'name'       => 'Support',
            'capability' => 'manage_options',
            'slug'       => Config::SLUG . '#/license',
        ];
    }
}
