<?php

namespace BitApps\Pi\src\Integrations\LemList;

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!\defined('ABSPATH')) {
    exit;
}


class LemListAction implements ActionInterface
{
    public const BASE_URL = 'https://api.lemlist.com/api/campaigns';

    private NodeInfoProvider $nodeInfoProvider;

    private LemListContact $lemListContact;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeLemListAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeLemListAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();

        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');

        $listId = $this->nodeInfoProvider->getFieldMapConfigs('campaigns-list-id.value');

        $dataArr = $this->nodeInfoProvider->getFieldMapRepeaters('contact-row.value', false, true, 'lemListField', 'value');

        $tokenAuthorization = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::BASIC_AUTH,
            $connectionId
        );

        $apiKey = $tokenAuthorization->getAccessToken();

        $header = [
            'Authorization' => $apiKey,
            'content-type'  => 'application/json'
        ];

        $this->lemListContact = new LemListContact(self::BASE_URL, $header);

        if ($machineSlug === 'createContact') {
            return $this->lemListContact->createNewContact($dataArr, $listId);
        }
    }
}
