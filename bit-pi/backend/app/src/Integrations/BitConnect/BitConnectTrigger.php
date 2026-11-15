<?php

namespace BitApps\Pi\src\Integrations\BitConnect;

use BitApps\Pi\Services\FlowService;
use BitApps\Pi\src\Integrations\IntegrationHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class BitConnectTrigger
{
    public static function postPublished($payload)
    {
        self::execute('postPublished', $payload);
    }

    public static function postStatusChanged($payload)
    {
        self::execute('postStatusChanged', $payload);
    }

    public static function postVoted($payload)
    {
        self::execute('postVoted', $payload);
    }

    public static function pollVoted($payload)
    {
        self::execute('pollVoted', $payload);
    }

    public static function commentAdded($payload)
    {
        self::execute('commentAdded', $payload);
    }

    public static function commentVoted($payload)
    {
        self::execute('commentVoted', $payload);
    }

    public static function memberRegistered($payload)
    {
        self::execute('memberRegistered', $payload);
    }

    public static function memberLoggedIn($payload)
    {
        self::execute('memberLoggedIn', $payload);
    }

    public static function badgeAwarded($payload)
    {
        self::execute('badgeAwarded', $payload);
    }

    public static function memberJoinedGroup($payload)
    {
        self::execute('memberJoinedGroup', $payload);
    }

    public static function memberLeftGroup($payload)
    {
        self::execute('memberLeftGroup', $payload);
    }

    public static function reportFiled($payload)
    {
        self::execute('reportFiled', $payload);
    }

    public static function reportsResolved($payload)
    {
        self::execute('reportsResolved', $payload);
    }

    public static function moderationActionTaken($payload)
    {
        self::execute('moderationActionTaken', $payload);
    }

    public static function postUpdated($payload)
    {
        self::execute('postUpdated', $payload);
    }

    public static function postDeleted($payload)
    {
        self::execute('postDeleted', $payload);
    }

    public static function postPinned($payload)
    {
        self::execute('postPinned', $payload);
    }

    public static function pollCreated($payload)
    {
        self::execute('pollCreated', $payload);
    }

    public static function commentUpdated($payload)
    {
        self::execute('commentUpdated', $payload);
    }

    public static function commentDeleted($payload)
    {
        self::execute('commentDeleted', $payload);
    }

    public static function commentPinned($payload)
    {
        self::execute('commentPinned', $payload);
    }

    public static function profileUpdated($payload)
    {
        self::execute('profileUpdated', $payload);
    }

    public static function itemFollowed($payload)
    {
        self::execute('itemFollowed', $payload);
    }

    public static function badgeRemoved($payload)
    {
        self::execute('badgeRemoved', $payload);
    }

    public static function groupJoinRequested($payload)
    {
        self::execute('groupJoinRequested', $payload);
    }

    public static function groupJoinRequestAnswered($payload)
    {
        self::execute('groupJoinRequestAnswered', $payload);
    }

    public static function anyForumEvent($event, $payload)
    {
        self::execute('anyForumEvent', $payload, $event, 'event-key');
    }

    private static function execute($machineSlug, $payload, $value = null, $keyName = 'form-id')
    {
        if (empty($payload) || !\is_array($payload)) {
            return;
        }

        $flows = FlowService::exists('bitConnect', $machineSlug);

        if (!$flows) {
            return;
        }

        IntegrationHelper::handleFlowForForm($flows, $payload, $value, $keyName, 'string');
    }
}
