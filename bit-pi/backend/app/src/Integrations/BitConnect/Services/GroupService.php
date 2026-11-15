<?php

namespace BitApps\Pi\src\Integrations\BitConnect\Services;

use BitApps\BitConnectPro\Integrations\Actions;
use BitApps\Pi\src\Integrations\IntegrationHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class GroupService extends BaseService
{
    public function addMemberToGroup()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'group_id' => ['required', 'integer'],
            'email'    => ['required', 'email'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $input = [
            'email' => $fieldMappings['email'],
            'role'  => empty($fieldMappings['role']) ? 'member' : $fieldMappings['role'],
        ];

        $groupMember = Actions::addToGroup((int) $fieldMappings['group_id'], $input);

        if (is_wp_error($groupMember)) {
            return [
                'status_code' => $this->getErrorStatus($groupMember),
                'payload'     => $fieldMappings,
                'response'    => $groupMember->get_error_message(),
            ];
        }

        return [
            'status_code' => 200,
            'payload'     => $fieldMappings,
            'response'    => $groupMember,
        ];
    }

    public function removeMemberFromGroup()
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'group_id' => ['required', 'integer'],
            'email'    => ['required', 'email'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $groupMember = Actions::removeFromGroup((int) $fieldMappings['group_id'], ['email' => $fieldMappings['email']]);

        if (is_wp_error($groupMember)) {
            return [
                'status_code' => $this->getErrorStatus($groupMember),
                'payload'     => $fieldMappings,
                'response'    => $groupMember->get_error_message(),
            ];
        }

        return [
            'status_code' => 200,
            'payload'     => $fieldMappings,
            'response'    => $groupMember,
        ];
    }
}
