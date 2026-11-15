<?php

namespace BitApps\Pi\src\Integrations\BitConnect\Services;

use BitApps\BitConnectPro\Integrations\Actions;
use BitApps\Pi\src\Integrations\BitConnect\helper\BitConnectHelper;
use BitApps\Pi\src\Integrations\IntegrationHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class PostService extends BaseService
{
    public function createPost()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'post_title'    => ['required'],
            'author_email'  => ['required', 'email'],
            'topic_type_id' => ['nullable', 'integer'],
            'group_id'      => ['nullable', 'integer'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $tags = $fieldMappings['tag_ids'] ?? [];
        $tags = \is_array($tags) ? $tags : explode(',', (string) $tags);
        $tags = array_map(
            static function ($tag) {
                $tag = trim((string) $tag);

                return ctype_digit($tag) ? (int) $tag : $tag;
            },
            $tags
        );
        $tags = array_values(array_filter($tags));

        $input = IntegrationHelper::filterEmpty(
            [
                'post_title'   => (string) $fieldMappings['post_title'],
                'post_content' => (string) ($fieldMappings['post_content'] ?? ''),
                'post_name'    => (string) ($fieldMappings['post_name'] ?? ''),
                'topic-types'  => empty($fieldMappings['topic_type_id']) ? '' : (int) $fieldMappings['topic_type_id'],
                'groups'       => empty($fieldMappings['group_id']) ? '' : (int) $fieldMappings['group_id'],
                'tags'         => $tags,
                'author_email' => $fieldMappings['author_email'],
            ]
        );

        $post = Actions::createTopic($input);

        if (is_wp_error($post)) {
            $errorData = $post->get_error_data();
            $isTopicTypeMissing = \is_array($errorData) && isset($errorData['errors']['topic-types']) && !isset($input['topic-types']);

            return [
                'status_code' => $this->getErrorStatus($post),
                'payload'     => $input,
                'response'    => $isTopicTypeMissing
                    ? __('This forum needs a topic type for each post. Select a topic type.', 'bit-pi')
                    : $post->get_error_message(),
            ];
        }

        return [
            'status_code' => 200,
            'payload'     => $input,
            'response'    => $post,
        ];
    }

    public function getPost()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'post_id' => ['required', 'integer'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $post = Actions::getTopic((int) $fieldMappings['post_id']);

        if (is_wp_error($post)) {
            return [
                'status_code' => $this->getErrorStatus($post),
                'payload'     => $fieldMappings,
                'response'    => $post->get_error_message(),
            ];
        }

        return [
            'status_code' => 200,
            'payload'     => $fieldMappings,
            'response'    => $post,
        ];
    }

    public function listPosts()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'topic_type_id' => ['nullable', 'integer'],
            'limit'         => ['nullable', 'integer'],
            'page'          => ['nullable', 'integer'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $pageArgs = $this->getPageArgs($fieldMappings);

        $queryArgs = [
            'post_type'      => BitConnectHelper::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $pageArgs['limit'],
            'paged'          => $pageArgs['page'],
            'orderby'        => 'date',
            'order'          => ($fieldMappings['sort'] ?? '') === 'oldest' ? 'ASC' : 'DESC',
            'fields'         => 'ids',
        ];

        if (!empty($fieldMappings['search'])) {
            $queryArgs['s'] = $fieldMappings['search'];
        }

        if (!empty($fieldMappings['topic_type_id'])) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            $queryArgs['tax_query'] = [
                [
                    'taxonomy' => BitConnectHelper::TOPIC_TYPE_TAXONOMY,
                    'field'    => 'term_id',
                    'terms'    => [(int) $fieldMappings['topic_type_id']],
                ],
            ];
        }

        $posts = [];

        foreach (get_posts($queryArgs) as $postId) {
            $post = Actions::getTopic((int) $postId);

            if (!is_wp_error($post)) {
                $posts[] = $post;
            }
        }

        return [
            'status_code' => 200,
            'payload'     => $queryArgs,
            'response'    => [
                'posts' => $posts,
                'count' => \count($posts),
                'page'  => $pageArgs['page'],
            ],
        ];
    }

    public function setPostStatus()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'post_id'   => ['required', 'integer'],
            'status_id' => ['nullable', 'integer'],
            'stage_id'  => ['nullable', 'integer'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $input = IntegrationHelper::filterEmpty(
            [
                'status_id' => empty($fieldMappings['status_id']) ? '' : (int) $fieldMappings['status_id'],
                'stage_id'  => empty($fieldMappings['stage_id']) ? '' : (int) $fieldMappings['stage_id'],
            ]
        );

        if (empty($input)) {
            return [
                'status_code' => 400,
                'payload'     => $fieldMappings,
                'response'    => __('Status and stage are empty. Select a status, a stage, or both.', 'bit-pi'),
            ];
        }

        $post = Actions::setTopicStatus((int) $fieldMappings['post_id'], $input);

        if (is_wp_error($post)) {
            return [
                'status_code' => $this->getErrorStatus($post),
                'payload'     => $fieldMappings,
                'response'    => $post->get_error_message(),
            ];
        }

        return [
            'status_code' => 200,
            'payload'     => $fieldMappings,
            'response'    => $post,
        ];
    }

    public function voteForPost()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'post_id'      => ['required', 'integer'],
            'author_email' => ['required', 'email'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $vote = Actions::voteTopic((int) $fieldMappings['post_id'], ['author_email' => $fieldMappings['author_email']]);

        if (is_wp_error($vote)) {
            return [
                'status_code' => $this->getErrorStatus($vote),
                'payload'     => $fieldMappings,
                'response'    => $vote->get_error_message(),
            ];
        }

        return [
            'status_code' => 200,
            'payload'     => $fieldMappings,
            'response'    => $vote,
        ];
    }
}
