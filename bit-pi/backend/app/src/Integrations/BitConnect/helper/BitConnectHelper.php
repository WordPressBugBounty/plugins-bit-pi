<?php

namespace BitApps\Pi\src\Integrations\BitConnect\helper;

use BitApps\BitConnectPro\Integrations\Actions;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\Helpers\Utility;

if (!defined('ABSPATH')) {
    exit;
}

final class BitConnectHelper
{
    public const POST_TYPE = 'bit-connect';

    public const TAG_TAXONOMY = 'bit-connect-tags';

    public const TOPIC_TYPE_TAXONOMY = 'bit-connect-topic-types';

    public static function isActionsAvailable(): bool
    {
        return class_exists('BitApps\BitConnectPro\Integrations\Actions') && Actions::available();
    }

    public static function getPosts()
    {
        if (!post_type_exists(self::POST_TYPE)) {
            return Response::error(__('Bit Connect is not active. Activate Bit Connect.', 'bit-pi'));
        }

        $posts = get_posts(
            [
                'post_type'              => self::POST_TYPE,
                'post_status'            => 'any',
                'posts_per_page'         => -1,
                'orderby'                => 'date',
                'order'                  => 'DESC',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]
        );

        $options = array_map(
            static function ($post) {
                $label = $post->post_title;
                $status = get_post_status_object($post->post_status);

                if ($post->post_status !== 'publish' && $status) {
                    $label .= ' (' . $status->label . ')';
                }

                return ['label' => $label, 'value' => $post->ID];
            },
            $posts
        );

        return Response::success($options);
    }

    public static function getMembers()
    {
        $users = Utility::getUsers(['orderby' => 'display_name', 'order' => 'ASC']);

        $options = array_map(
            static function ($user) {
                return ['label' => $user['display_name'] . ' (' . $user['user_email'] . ')', 'value' => $user['user_email']];
            },
            $users
        );

        return Response::success($options);
    }

    public static function getTags()
    {
        if (!taxonomy_exists(self::TAG_TAXONOMY)) {
            return Response::error(__('Bit Connect is not active. Activate Bit Connect.', 'bit-pi'));
        }

        $terms = get_terms(['taxonomy' => self::TAG_TAXONOMY, 'hide_empty' => false]);

        if (is_wp_error($terms)) {
            return Response::error($terms->get_error_message());
        }

        $options = array_map(
            static function ($term) {
                return ['label' => $term->name, 'value' => $term->term_id];
            },
            $terms
        );

        return Response::success($options);
    }

    public static function getTopicTypes()
    {
        return self::getListOptions('topic_types');
    }

    public static function getStatuses()
    {
        return self::getListOptions('statuses');
    }

    public static function getStages()
    {
        return self::getListOptions('stages');
    }

    public static function getBadges()
    {
        return self::getListOptions('badges');
    }

    public static function getGroups()
    {
        return self::getListOptions('groups');
    }

    private static function getListOptions($listKey)
    {
        if (!self::isActionsAvailable()) {
            return Response::error(__('Bit Connect Pro is not active or has no active licence. Activate Bit Connect Pro and its licence.', 'bit-pi'));
        }

        $lists = Actions::lists();

        if (is_wp_error($lists)) {
            return Response::error($lists->get_error_message());
        }

        $options = array_map(
            static function ($item) {
                return ['label' => $item['name'], 'value' => $item['id']];
            },
            $lists[$listKey] ?? []
        );

        return Response::success($options);
    }
}
