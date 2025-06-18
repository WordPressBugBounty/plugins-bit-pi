<?php

namespace BitApps\Pi\src\Integrations\Drip;

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!\defined('ABSPATH')) {
    exit;
}


class DripAction implements ActionInterface
{
    public const BASE_URL = 'https://api.getdrip.com/v2';

    private const STATIC_FIELD_KEYS = [
        'email',
        'first_name',
        'last_name',
        'address1',
        'address2',
        'city',
        'state',
        'zip',
        'country',
        'phone',
        'time_zone',
        'ip_address',
    ];

    private NodeInfoProvider $nodeInfoProvider;

    private DripService $dripContact;

    private $addTagIds;

    private $removeTagIds;

    private $subscriberStatus;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeDripAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeDripAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();

        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');

        $accountListId = $this->nodeInfoProvider->getFieldMapConfigs('account-list-id.value');

        $this->subscriberStatus = $this->nodeInfoProvider->getFieldMapConfigs('subscriber-status.value');

        $this->addTagIds = $this->nodeInfoProvider->getFieldMapConfigs('add-tag-ids.value');

        $this->removeTagIds = $this->nodeInfoProvider->getFieldMapConfigs('remove-tag-ids.value');

        $dataArr = $this->nodeInfoProvider->getFieldMapRepeaters('contact-row.value', false, true, 'dripField', 'value');

        $tokenAuthorization = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::BASIC_AUTH,
            $connectionId
        );

        $basic_auth_token = $tokenAuthorization->getAccessToken();

        $header = [
            'Authorization' => $basic_auth_token,
            'Content-type'  => 'application/json'
        ];

        $this->dripContact = new DripService(static::BASE_URL, $header);

        if ($machineSlug === 'createOrUpdateContact') {
            $apiData = $this->preprocessApiData($dataArr);

            return $this->dripContact->createOrUpdateContact($apiData, $accountListId);
        }
    }

    private function preprocessApiData($data = [])
    {
        $subscriberData = [];

        $customFieldsData = [];

        foreach ($data as $key => $value) {
            if (\in_array($key, self::STATIC_FIELD_KEYS)) {
                $subscriberData[$key] = $value;

                continue;
            }

            $customFieldsData[$key] = $value;
        }

        if ($customFieldsData !== []) {
            $subscriberData['custom_fields'] = (object) $customFieldsData;
        }

        if (!empty($this->subscriberStatus)) {
            $subscriberData['status'] = $this->subscriberStatus;
        }

        if (!empty($this->addTagIds)) {
            $subscriberData['tags'] = $this->addTagIds;
        }

        if (!empty($this->removeTagIds)) {
            $subscriberData['remove_tags'] = $this->removeTagIds;
        }

        return (object) [
            'subscribers' => [(object) $subscriberData]
        ];
    }
}
