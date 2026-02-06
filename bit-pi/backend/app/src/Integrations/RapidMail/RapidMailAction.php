<?php

namespace BitApps\Pi\src\Integrations\RapidMail;

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!\defined('ABSPATH')) {
    exit;
}


class RapidMailAction implements ActionInterface
{
    public const BASE_URL = 'https://apiv3.emailsys.net/v1';

    private NodeInfoProvider $nodeInfoProvider;

    private RapidMailService $rapidMailContact;

    private array $recipientTags = [];

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeRapidMailAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeRapidMailAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();

        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');

        $listId = $this->nodeInfoProvider->getFieldMapConfigs('list-id.value');
        $status = $this->nodeInfoProvider->getFieldMapConfigs('status.value');

        $this->recipientTags = $this->nodeInfoProvider->getFieldMapConfigs('recipient-tags.value');

        $dataArr = $this->nodeInfoProvider->getFieldMapRepeaters('contact-fields.value', false, true, 'rapidMailField', 'value');

        $tokenAuthorization = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::BASIC_AUTH,
            $connectionId
        );

        $apiKey = $tokenAuthorization->getAccessToken();

        $header = [
            'Authorization' => $apiKey,
            'Content-Type'  => 'application/json',
        ];

        $this->rapidMailContact = new RapidMailService(static::BASE_URL, $header);

        if ($machineSlug === 'createContact') {
            $dataArr = $this->formattedData($listId, $status, $dataArr);

            return $this->rapidMailContact->addRecipient($dataArr);
        }
    }

    private function formattedData($listId, $status, $data = [])
    {
        $data['recipientlist_id'] = (int) $listId;

        if ($this->recipientTags !== []) {
            $data['tags'] = $this->recipientTags;
        }

        if ($status === true) {
            $data['status'] = 'active';
        }

        return $data;
    }
}
