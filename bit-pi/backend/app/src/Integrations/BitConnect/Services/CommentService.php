<?php

namespace BitApps\Pi\src\Integrations\BitConnect\Services;

use BitApps\BitConnect\Services\Events\EventPayload;
use BitApps\BitConnectPro\Integrations\Actions;
use BitApps\Pi\src\Integrations\IntegrationHelper;
use WP_Comment;

if (!defined('ABSPATH')) {
    exit;
}

final class CommentService extends BaseService
{
    public function addComment()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'post_id'      => ['required', 'integer'],
            'content'      => ['required'],
            'parent_id'    => ['nullable', 'integer'],
            'author_email' => ['required', 'email'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $input = IntegrationHelper::filterEmpty(
            [
                'content'      => (string) $fieldMappings['content'],
                'parent_id'    => empty($fieldMappings['parent_id']) ? '' : (int) $fieldMappings['parent_id'],
                'author_email' => $fieldMappings['author_email'],
            ]
        );

        $comment = Actions::addComment((int) $fieldMappings['post_id'], $input);

        if (is_wp_error($comment)) {
            return [
                'status_code' => $this->getErrorStatus($comment),
                'payload'     => $fieldMappings,
                'response'    => $comment->get_error_message(),
            ];
        }

        return [
            'status_code' => 200,
            'payload'     => $fieldMappings,
            'response'    => $comment,
        ];
    }

    public function listComments()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'post_id' => ['required', 'integer'],
            'limit'   => ['nullable', 'integer'],
            'page'    => ['nullable', 'integer'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $postId = (int) $fieldMappings['post_id'];
        $post = Actions::getTopic($postId);

        if (is_wp_error($post)) {
            return [
                'status_code' => $this->getErrorStatus($post),
                'payload'     => $fieldMappings,
                'response'    => $post->get_error_message(),
            ];
        }

        $pageArgs = $this->getPageArgs($fieldMappings);

        $queryArgs = [
            'post_id' => $postId,
            'status'  => 'approve',
            'type'    => 'comment',
            'number'  => $pageArgs['limit'],
            'offset'  => ($pageArgs['page'] - 1) * $pageArgs['limit'],
            'orderby' => 'comment_date_gmt',
            'order'   => 'ASC',
        ];

        $comments = [];

        foreach (get_comments($queryArgs) as $comment) {
            if ($comment instanceof WP_Comment) {
                $comments[] = EventPayload::comment($comment) + EventPayload::user((int) $comment->user_id, 'author');
            }
        }

        return [
            'status_code' => 200,
            'payload'     => $queryArgs,
            'response'    => [
                'comments' => $comments,
                'count'    => \count($comments),
                'page'     => $pageArgs['page'],
            ],
        ];
    }
}
