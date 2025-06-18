<?php

namespace BitApps\Pi\src\Integrations\Encharge;

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!\defined('ABSPATH')) {
    exit;
}


class EnchargeAction implements ActionInterface
{
    public const BASE_URL = 'https://api.encharge.io/v1';

    private NodeInfoProvider $nodeInfoProvider;

    private EnchargeContact $enchargeContact;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeEnchargeAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeEnchargeAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();

        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');

        $tagList = $this->nodeInfoProvider->getFieldMapConfigs('tag-list.value');

        $dataArr = $this->nodeInfoProvider->getFieldMapRepeaters('contact-row.value', false, true, 'enchargeField', 'value');

        $tokenAuthorization = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::API_KEY,
            $connectionId
        );

        $apiKey = $tokenAuthorization->getAccessToken();

        $header = [
            'accept'           => 'application/json',
            'X-Encharge-Token' => $apiKey,
            'content-type'     => 'application/json'
        ];

        $this->enchargeContact = new EnchargeContact(static::BASE_URL, $header);

        if ($machineSlug === 'createContact') {
            $dataArr = $this->formattedData($tagList, $dataArr);

            return $this->enchargeContact->createNewContact($dataArr);
        }
    }

    private function formattedData($tagList, $data = [])
    {
        $splitTags = implode(', ', $tagList);
        $data['tags'] = $splitTags;

        return $data;
    }
}
