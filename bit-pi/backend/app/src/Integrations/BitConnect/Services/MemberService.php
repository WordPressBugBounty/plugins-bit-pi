<?php

namespace BitApps\Pi\src\Integrations\BitConnect\Services;

use BitApps\BitConnectPro\Integrations\Actions;
use BitApps\Pi\src\Integrations\IntegrationHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class MemberService extends BaseService
{
    public function findMember()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'email' => ['required', 'email'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $member = Actions::findMember(['email' => $fieldMappings['email']]);

        if (is_wp_error($member)) {
            return [
                'status_code' => $this->getErrorStatus($member),
                'payload'     => $fieldMappings,
                'response'    => $member->get_error_message(),
            ];
        }

        return [
            'status_code' => 200,
            'payload'     => $fieldMappings,
            'response'    => $member,
        ];
    }

    public function awardBadge()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'email'    => ['required', 'email'],
            'badge_id' => ['required'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $member = Actions::findMember(['email' => $fieldMappings['email']]);

        if (is_wp_error($member)) {
            return [
                'status_code' => $this->getErrorStatus($member),
                'payload'     => $fieldMappings,
                'response'    => $member->get_error_message(),
            ];
        }

        $awardedMember = Actions::awardBadge((int) $member['member_id'], (string) $fieldMappings['badge_id']);

        if (is_wp_error($awardedMember)) {
            return [
                'status_code' => $this->getErrorStatus($awardedMember),
                'payload'     => $fieldMappings,
                'response'    => $awardedMember->get_error_message(),
            ];
        }

        return [
            'status_code' => 200,
            'payload'     => $fieldMappings,
            'response'    => $awardedMember,
        ];
    }
}
