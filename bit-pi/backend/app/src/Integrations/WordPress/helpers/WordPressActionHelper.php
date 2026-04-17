<?php

namespace BitApps\Pi\src\Integrations\WordPress\helpers;

use BitApps\Pi\Config;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\Model\FlowNode;
use BitApps\Pi\src\Integrations\WpActionHookListener\WpActionHookListener;
use WP_Roles;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

class WordPressActionHelper
{
    public static function setPostFeaturedImage($postId, $fields, $isEnabledFeaturedImageId = false)
    {
        $imageField = $isEnabledFeaturedImageId ? 'featuredImageId' : 'featuredImage';
        if (empty($fields[$imageField])) {
            return;
        }

        $attachmentId = null;

        if ($imageField === 'featuredImageId') {
            $attachmentId = \intval($fields[$imageField]);
        } elseif ($imageField === 'featuredImage') {
            if (!\function_exists('wp_handle_upload')) {
                include_once ABSPATH . 'wp-admin/includes/file.php';
            }

            if (!\function_exists('wp_insert_attachment')) {
                include_once ABSPATH . 'wp-admin/includes/media.php';

                include_once ABSPATH . 'wp-admin/includes/image.php';
            }

            $featuredImage = filter_var($fields[$imageField] ?? '', FILTER_SANITIZE_URL);

            $attachmentId = media_sideload_image($featuredImage, 0, null, 'id');

            if (is_wp_error($attachmentId)) {
                return self::response(__('Failed to upload featured image.', 'bit-pi'), $fields, (int) $attachmentId->get_error_code());
            }
        }

        if (empty($attachmentId)) {
            return self::response(__('Featured image data is required.', 'bit-pi'), $fields, 422);
        }

        if (!wp_attachment_is_image($attachmentId)) {
            return self::response(__('Invalid featured image attachment ID.', 'bit-pi'), $fields, 422);
        }

        if (!set_post_thumbnail($postId, $attachmentId)) {
            return self::response(__('Failed to set post thumbnail.', 'bit-pi'), $fields, 500);
        }
    }

    /**
     * Get Roles.
     *
     * @return array
     */
    public static function getWpRoles()
    {
        global $wp_roles;

        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }

        return $wp_roles->roles ?? [];
    }

    /**
     * Get WP Posts.
     *
     * @param null|string $postType
     * @param null|array $metaQuery
     * @param string $status
     * @param null|string $search
     *
     * @return array
     */
    public static function getPosts($postType = null, $metaQuery = null, $search = null, $status = 'any')
    {
        $args = [
            'posts_per_page' => -1,
            'post_status'    => $status
        ];

        if (!empty($postType)) {
            $args['post_type'] = $postType;
        }

        if (!empty($metaQuery)) {
            $args['meta_query'] = $metaQuery; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for WP_Query meta filtering
        }

        if (!empty($search)) {
            $args['s'] = $search;
        }

        return get_posts($args);
    }

    /**
     * Get WP Posts Comments.
     *
     * @param null|int $postId
     * @param null|int $userId
     * @param null|string $email
     *
     * @return array
     */
    public static function getComments($postId = null, $userId = null, $email = null)
    {
        $args = ['order' => 'ASC'];

        if (!empty($postId)) {
            $args['post_id'] = $postId;
        }

        if (!empty($userId)) {
            $args['user_id'] = $userId;
        }

        if (!empty($email)) {
            $args['author_email'] = $email;
        }

        return get_comments($args);
    }

    /**
     * Get Term Execute.
     *
     * @param string $taxonomy
     * @param array $payload
     *
     * @return array
     */
    public static function getTermsExecute($taxonomy = null, $payload = [])
    {
        $args = [
            'orderby'    => 'term_id',
            'hide_empty' => false
        ];

        if (!empty($taxonomy)) {
            $args['taxonomy'] = $taxonomy;
        }

        return self::response(get_terms($args), $payload);
    }

    /**
     * Delete Term Execute.
     *
     * @param int $termId
     * @param string $taxonomy
     * @param array $payload
     * @param string $successMsg
     * @param string $errorMsg
     * @param array $args
     *
     * @return array
     */
    public static function deleteTermExecute($termId, $taxonomy, $payload, $successMsg, $errorMsg, $args = [])
    {
        if (empty($termId)) {
            return self::response(__('Id is required', 'bit-pi'), $payload, 422);
        }

        if (empty($taxonomy)) {
            return self::response(__('Taxonomy is required', 'bit-pi'), $payload, 422);
        }

        if (empty(get_term($termId, $taxonomy))) {
            return self::response(__('Not found', 'bit-pi'), $payload, 400);
        }

        $status = wp_delete_term($termId, $taxonomy, $args);

        if (!$status) {
            return self::response($errorMsg, $payload, 500);
        }

        return self::response($successMsg, $payload);
    }

    /**
     * Update Term Execute.
     *
     * @param int $termId
     * @param string $taxonomy
     * @param array $payload
     * @param null|string $name
     * @param null|string $slug
     * @param null|string $description
     *
     * @return array
     */
    public static function updateTermExecute($termId, $taxonomy, $payload, $name = null, $slug = null, $description = null)
    {
        if (empty($termId)) {
            return self::response(__('Id is required', 'bit-pi'), $payload, 422);
        }

        if (empty($taxonomy)) {
            return self::response(__('Taxonomy is required', 'bit-pi'), $payload, 422);
        }

        if (empty(get_term($termId, $taxonomy))) {
            return self::response(__('Not found', 'bit-pi'), $payload, 400);
        }

        $args = array_filter(['name' => $name, 'slug' => $slug, 'description' => $description]);

        if (empty($args)) {
            return self::response(__('Nothing to update.', 'bit-pi'), $payload, 400);
        }

        $term = wp_update_term($termId, $taxonomy, $args);

        if (is_wp_error($term)) {
            return self::response($term->get_error_message(), $payload, 500);
        }

        return self::response($term, $payload);
    }

    /**
     * Insert Term Execute.
     *
     * @param string $taxonomy
     * @param array $payload
     * @param null|string $name
     * @param null|string $slug
     * @param null|string $description
     *
     * @return array
     */
    public static function insertTermExecute($name, $taxonomy, $payload, $slug = null, $description = null)
    {
        if (empty($name)) {
            return self::response(__('Name is required', 'bit-pi'), $payload, 422);
        }

        if (empty($taxonomy)) {
            return self::response(__('Taxonomy is required', 'bit-pi'), $payload, 422);
        }

        $term = wp_insert_term($name, $taxonomy, ['slug' => $slug, 'description' => $description]);

        if (is_wp_error($term)) {
            return self::response($term->get_error_message(), $payload, 500);
        }

        return self::response($term, $payload);
    }

    /**
     * Get Term Execute.
     *
     * @param int $termId
     * @param string $taxonomy
     * @param array $payload
     *
     * @return array
     */
    public static function getTermExecute($termId, $taxonomy, $payload)
    {
        if (empty($termId)) {
            return self::response(__('Id is required', 'bit-pi'), $payload, 422);
        }

        if (empty($taxonomy)) {
            return self::response(__('Taxonomy is required', 'bit-pi'), $payload, 422);
        }

        $term = get_term($termId, $taxonomy);

        if (empty($term)) {
            return self::response(__('Not found with provided id', 'bit-pi'), $payload, 400);
        }

        return self::response($term, $payload);
    }

    /**
     * Update wordpress User Field Mapping.
     *
     * @param array $userData
     *
     * @return array
     */
    public static function mapUserFields($userData)
    {
        $fields = [
            'username'     => 'user_login',
            'email'        => 'user_email',
            'nickname'     => 'nickname',
            'display_name' => 'display_name',
            'first_name'   => 'first_name',
            'last_name'    => 'last_name',
            'user_url'     => 'user_url',
            'description'  => 'description',
        ];

        $fieldMap = [];

        foreach ($fields as $inputKey => $dbKey) {
            if (!empty($userData[$inputKey])) {
                $fieldMap[$dbKey] = $userData[$inputKey];
            }
        }

        return $fieldMap;
    }

    /**
     * Post Payload Mapping.
     *
     * @param array $data
     *
     * @return array
     */
    public static function mapPostPayload($data)
    {
        $fields = [
            'title'           => 'title',
            'content'         => 'content',
            'date'            => 'date',
            'date_gmt'        => 'dateGMT',
            'excerpt'         => 'excerpt',
            'slug'            => 'slug',
            'parent_id'       => 'parentId',
            'featured_image'  => 'featuredImage',
            'password'        => 'password',
            'post_type'       => 'postType',
            'post_categories' => 'categories',
            'post_tags'       => 'tags',
            'post_status'     => 'postStatus',
            'post_author'     => 'postAuthor',
            'taxonomy'        => 'taxonomy',
            'terms'           => 'terms',
            'custom_field'    => 'customField',
        ];

        return array_map(
            function ($dbKey) use ($data) {
                return $data[$dbKey] ?? null;
            },
            $fields
        );
    }

    /**
     * Post FIelds Mapping.
     *
     * @param array $data
     *
     * @return array
     */
    public static function mapPostFields($data)
    {
        $fields = [
            'ID'             => 'postId',
            'post_title'     => 'title',
            'post_content'   => 'content',
            'post_status'    => 'postStatus',
            'post_type'      => 'postType',
            'meta_input'     => 'customField',
            'post_date'      => 'date',
            'post_date_gmt'  => 'dateGMT',
            'post_password'  => 'password',
            'post_parent'    => 'parentId',
            'post_author_id' => 'postAuthor',
            'post_excerpt'   => 'excerpt',
            'post_name'      => 'slug',
            'post_category'  => 'categories',
        ];

        $fieldMap = [];

        foreach ($fields as $inputKey => $dbKey) {
            if (!empty($data[$dbKey])) {
                $fieldMap[$inputKey] = $data[$dbKey];
            }
        }

        return $fieldMap;
    }

    /**
     * Comment Field Mapping.
     *
     * @param array $data
     *
     * @return array
     */
    public static function mapCommentFields($data)
    {
        return [
            'comment_post_ID'      => $data['postId'] ?? null,
            'comment_author'       => $data['authorName'] ?? null,
            'comment_author_email' => $data['authorEmail'] ?? null,
            'comment_author_url'   => $data['authorURL'] ?? null,
            'comment_content'      => $data['comment'] ?? null,
            'comment_type'         => 'comment',
            'comment_status'       => 'approved',
            'comment_parent'       => $data['parentId'] ?? 0,
            'comment_author_IP'    => '',
            'comment_agent'        => 'Bit Flows/' . Config::VERSION,
            'comment_date'         => gmdate('Y-m-d H:i:s'),
            'comment_approved'     => 1,
        ];
    }

    /**
     * Validate required input.
     */
    public static function validateUserCreateInput(array $data, array $payload): ?array
    {
        if (empty($data['email'])) {
            return WordPressActionHelper::response(__('Email is required', 'bit-pi'), $payload, 422);
        }

        if (empty($data['username'])) {
            return WordPressActionHelper::response(__('Username is required', 'bit-pi'), $payload, 422);
        }

        if (get_user_by('email', $data['email'])) {
            return WordPressActionHelper::response(__('User already exists', 'bit-pi'), $payload, 422);
        }

        return null;
    }

    /**
     * Return standardized error response.
     *
     * @param array|object|string $response
     * @param array $payload
     * @param int $code
     *
     * @return array
     */
    public static function response($response, $payload = [], $code = 200)
    {
        if (!\is_array($response)) {
            $response = [$response];
        }

        return Utility::formatResponseData($code, $payload, $response);
    }

    /**
     * Register user defined hooks.
     *
     * @param string $appSlug
     * @param array  $triggers
     */
    public static function registerUserDefinedHooks($appSlug, $triggers = [])
    {
        $flowNodes = FlowNode::select(['field_mapping'])->where('app_slug', $appSlug)->get();

        if (!\is_array($flowNodes)) {
            return $triggers;
        }

        foreach ($flowNodes as $flowNode) {
            $hookName = $flowNode->field_mapping->configs->{'hook-name'}->value ?? null;

            if (!$hookName) {
                continue;
            }

            $machineSlug = Utility::convertToMachineSlug($hookName);

            $triggers[$machineSlug] = [
                'hook'          => $hookName,
                'callback'      => [new WpActionHookListener($appSlug, 'addAction'), 'captureHookData'],
                'accepted_args' => PHP_INT_MAX
            ];
        }

        $triggers['doAction'] = [
            'hook'          => Config::VAR_PREFIX . 'do_action',
            'callback'      => [new WpActionHookListener($appSlug, 'doAction'), 'captureHookData'],
            'accepted_args' => PHP_INT_MAX
        ];

        return $triggers;
    }
}
