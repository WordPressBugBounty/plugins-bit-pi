<?php

namespace BitApps\Pi\src\Integrations\WordPress;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\Helpers\Utility;
use WP_Roles;

class WordPressHelper
{
    public function getUsers()
    {
        $users = Utility::getUsers();

        $allUsers = array_map(
            function ($user) {
                return [
                    'label' => $user['user_login'],
                    'value' => $user['user_id']
                ];
            },
            $users
        );

        return Response::success($allUsers);
    }

    public function getUserRoles()
    {
        $roles = [];

        global $wp_roles;

        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }

        foreach ($wp_roles->roles as $key => $role) {
            $roles[] = [
                'label' => $role['name'],
                'value' => $key
            ];
        }

        return Response::success($roles);
    }

    public function getGenerateUserPassword()
    {
        return Response::success(wp_generate_password());
    }

    public function getPostTypes()
    {
        $allPostTypes = [];

        foreach (get_post_types(['public' => true], 'objects') as $postType) {
            $allPostTypes[] = [
                'label' => $postType->label,
                'value' => $postType->name
            ];
        }

        return Response::success($allPostTypes);
    }

    public function getPosts(Request $request)
    {
        $validated = $request->validate(
            [
                'postType' => ['nullable', 'array'],
            ]
        );

        $posts = get_posts(
            [
                'post_type'      => \is_array($validated['postType']) && \count($validated['postType']) ? $validated['postType'] : get_post_types(['public' => true]),
                'posts_per_page' => -1,
                'post_status'    => 'any',
            ]
        );

        $allPosts = [];

        foreach ($posts as $post) {
            $allPosts[] = [
                'label' => $post->post_title,
                'value' => $post->ID
            ];
        }

        return Response::success($allPosts);
    }

    public function getPostTags()
    {
        return $this->getTermsBy('post_tag');
    }

    public function getTaxonomies()
    {
        $taxonomies = get_taxonomies([], 'objects');

        $allTaxonomies = array_values(
            array_map(
                function ($taxonomy) {
                    return [
                        'label' => $taxonomy->label,
                        'value' => $taxonomy->name
                    ];
                },
                $taxonomies
            )
        );

        return Response::success($allTaxonomies);
    }

    public function getPostCategory()
    {
        return $this->getTermsBy('category');
    }

    public function getTermsByTaxonomy(Request $request)
    {
        $taxonomy = sanitize_text_field($request->taxonomy);

        if (!taxonomy_exists($taxonomy)) {
            return Response::error('Invalid taxonomy.');
        }

        return $this->getTermsBy($taxonomy, 'term_id', false, 'slug');
    }

    /**
     * Generic method to retrieve and format terms.
     *
     * @param string $taxonomy
     * @param string $orderby
     * @param bool $hideEmpty
     * @param string $valueKey
     */
    private function getTermsBy($taxonomy, $orderby = 'term_id', $hideEmpty = false, $valueKey = 'term_id')
    {
        $terms = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'orderby'    => $orderby,
                'hide_empty' => $hideEmpty,
            ]
        );

        if (is_wp_error($terms)) {
            return Response::error('Failed to retrieve terms.');
        }

        $formatted = array_map(
            function ($term) use ($valueKey) {
                return [
                    'label' => $term->name,
                    'value' => $term->{$valueKey},
                ];
            },
            $terms
        );

        return Response::success(array_values($formatted));
    }
}
