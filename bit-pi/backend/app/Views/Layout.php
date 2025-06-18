<?php

// phpcs:disable Squiz.NamingConventions.ValidVariableName.NotCamelCaps

namespace BitApps\Pi\Views;

use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Hooks\Hooks;
use BitApps\Pi\Deps\BitApps\WPKit\Utils\Capabilities;

if (!\defined('ABSPATH')) {
    exit;
}


/**
 * The admin Layout and page handler class.
 */
final class Layout
{
    public function __construct()
    {
        Hooks::addAction('in_admin_header', [$this, 'removeAdminNotices']);
        Hooks::addAction('admin_menu', [$this, 'sideBarMenuItem']);
        Hooks::addAction('admin_enqueue_scripts', [new Head(), 'addHeadScripts'], 0);
    }

    /**
     * Register the admin left sidebar menu item.
     */
    public function sideBarMenuItem()
    {
        $menus = Hooks::applyFilter(Config::withPrefix('admin_sidebar_menu'), Config::get('SIDE_BAR_MENU'));
        global $submenu;

        foreach ($menus as $menu) {
            if (isset($menu['capability']) && Capabilities::check($menu['capability'])) {
                if ($menu['type'] === 'menu') {
                    add_menu_page(
                        $menu['title'],
                        $menu['name'],
                        $menu['capability'],
                        $menu['slug'],
                        $menu['callback'],
                        $menu['icon'],
                        $menu['position']
                    );
                } else {
                    $submenu[$menu['parent']][] = [$menu['name'], $menu['capability'], 'admin.php?page=' . $menu['slug']];
                }
            }
        }
    }

    public function removeAdminNotices()
    {
        global $plugin_page;

        if (empty($plugin_page) || strpos($plugin_page, Config::SLUG) === false) {
            return;
        }

        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
    }
}
