<?php

namespace BitApps\Pi\src\Integrations\BitConnect;

use BitApps\Pi\src\Integrations\HookRegisterInterface;

if (!defined('ABSPATH')) {
    exit;
}

class BitConnectHooks implements HookRegisterInterface
{
    public function register(): array
    {
        return [
            'postPublished' => [
                'hook'          => 'bit_connect_post_created',
                'callback'      => [BitConnectTrigger::class, 'postPublished'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'postStatusChanged' => [
                'hook'          => 'bit_connect_post_status_changed',
                'callback'      => [BitConnectTrigger::class, 'postStatusChanged'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'postVoted' => [
                'hook'          => 'bit_connect_post_voted',
                'callback'      => [BitConnectTrigger::class, 'postVoted'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'pollVoted' => [
                'hook'          => 'bit_connect_poll_voted',
                'callback'      => [BitConnectTrigger::class, 'pollVoted'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'commentAdded' => [
                'hook'          => 'bit_connect_comment_created',
                'callback'      => [BitConnectTrigger::class, 'commentAdded'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'commentVoted' => [
                'hook'          => 'bit_connect_comment_voted',
                'callback'      => [BitConnectTrigger::class, 'commentVoted'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'memberRegistered' => [
                'hook'          => 'bit_connect_member_registered',
                'callback'      => [BitConnectTrigger::class, 'memberRegistered'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'memberLoggedIn' => [
                'hook'          => 'bit_connect_member_logged_in',
                'callback'      => [BitConnectTrigger::class, 'memberLoggedIn'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'badgeAwarded' => [
                'hook'          => 'bit_connect_badge_awarded',
                'callback'      => [BitConnectTrigger::class, 'badgeAwarded'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'memberJoinedGroup' => [
                'hook'          => 'bit_connect_group_joined',
                'callback'      => [BitConnectTrigger::class, 'memberJoinedGroup'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'memberLeftGroup' => [
                'hook'          => 'bit_connect_group_left',
                'callback'      => [BitConnectTrigger::class, 'memberLeftGroup'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'reportFiled' => [
                'hook'          => 'bit_connect_report_filed',
                'callback'      => [BitConnectTrigger::class, 'reportFiled'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'reportsResolved' => [
                'hook'          => 'bit_connect_report_resolved',
                'callback'      => [BitConnectTrigger::class, 'reportsResolved'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'moderationActionTaken' => [
                'hook'          => 'bit_connect_moderation_action',
                'callback'      => [BitConnectTrigger::class, 'moderationActionTaken'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'postUpdated' => [
                'hook'          => 'bit_connect_post_updated',
                'callback'      => [BitConnectTrigger::class, 'postUpdated'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'postDeleted' => [
                'hook'          => 'bit_connect_post_deleted',
                'callback'      => [BitConnectTrigger::class, 'postDeleted'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'postPinned' => [
                'hook'          => 'bit_connect_post_pinned',
                'callback'      => [BitConnectTrigger::class, 'postPinned'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'pollCreated' => [
                'hook'          => 'bit_connect_poll_created',
                'callback'      => [BitConnectTrigger::class, 'pollCreated'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'commentUpdated' => [
                'hook'          => 'bit_connect_comment_updated',
                'callback'      => [BitConnectTrigger::class, 'commentUpdated'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'commentDeleted' => [
                'hook'          => 'bit_connect_comment_deleted',
                'callback'      => [BitConnectTrigger::class, 'commentDeleted'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'commentPinned' => [
                'hook'          => 'bit_connect_comment_pinned',
                'callback'      => [BitConnectTrigger::class, 'commentPinned'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'profileUpdated' => [
                'hook'          => 'bit_connect_profile_updated',
                'callback'      => [BitConnectTrigger::class, 'profileUpdated'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'itemFollowed' => [
                'hook'          => 'bit_connect_followed',
                'callback'      => [BitConnectTrigger::class, 'itemFollowed'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'badgeRemoved' => [
                'hook'          => 'bit_connect_badge_removed',
                'callback'      => [BitConnectTrigger::class, 'badgeRemoved'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'groupJoinRequested' => [
                'hook'          => 'bit_connect_group_join_requested',
                'callback'      => [BitConnectTrigger::class, 'groupJoinRequested'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'groupJoinRequestAnswered' => [
                'hook'          => 'bit_connect_group_request_answered',
                'callback'      => [BitConnectTrigger::class, 'groupJoinRequestAnswered'],
                'priority'      => 10,
                'accepted_args' => 1,
            ],
            'anyForumEvent' => [
                'hook'          => 'bit_connect_event',
                'callback'      => [BitConnectTrigger::class, 'anyForumEvent'],
                'priority'      => 10,
                'accepted_args' => 2,
            ],
        ];
    }
}
