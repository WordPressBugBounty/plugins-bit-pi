<?php

namespace BitApps\Pi\src\Integrations\Brevo;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Integrations\Brevo\helpers\BrevoActionHelper;
use BitApps\Pi\src\Interfaces\ActionInterface;

class BrevoAction implements ActionInterface
{
    private const BASE_URL = 'https://api.brevo.com/v3';

    private NodeInfoProvider $nodeInfoProvider;

    private BrevoService $brevoService;

    private $machineSlug;

    private $configs;

    private $fieldMapData;

    private $email;

    private $header;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeBrevoAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function setNodeInfoProperties()
    {
        $this->machineSlug = $this->nodeInfoProvider->getMachineSlug();
        $this->configs = $this->nodeInfoProvider->getFieldMapConfigs();
        $emailList = $this->nodeInfoProvider->getFieldMapRepeaters('email-list.value', false, false);
        $attributes = $this->nodeInfoProvider->getFieldMapRepeaters('attributes-list.value', false, true, 'brevoField');
        $smtpList = $this->nodeInfoProvider->getFieldMapRepeaters('smtp-sender-list.value', false, false);
        $fieldMapData = $this->nodeInfoProvider->getFieldMapData();

        if (!empty($attributes)) {
            $fieldMapData = array_merge($fieldMapData, ['attributes' => $attributes]);
        }

        if (!empty($smtpList)) {
            $id = 'smtp-sender';
            $smtpSender = BrevoActionHelper::handleConditions($smtpList, $id);
            $fieldMapData = array_merge($fieldMapData, ['smtpBlacklistSender' => $smtpSender]);
        }

        $this->email = BrevoActionHelper::handleConditions($emailList, $id = 'email');
        $fieldMapData = BrevoActionHelper::handleBooleanParameter($fieldMapData);
        $this->fieldMapData = $fieldMapData;

        $tokenAuthorization = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::API_KEY,
            $this->configs['connection-id']
        );

        $apiKey = $tokenAuthorization->getAccessToken();

        $this->header = [
            'accept'       => 'application/json',
            'api-key'      => $apiKey,
            'content-type' => 'application/json'
        ];
    }

    private function executeAction()
    {
        switch ($this->machineSlug) {
            case 'createContact':
                $fieldMapData = array_merge($this->fieldMapData, ['listIds' => [$this->configs['list-id']['value']]]);

                return $this->brevoService->createContact($fieldMapData);

            case 'addContactToList':
                return $this->brevoService->addContactToList($this->configs['list-id']['value'], $this->email);

            case 'deleteContact':
                return $this->brevoService->deleteContact($this->fieldMapData['email']);

            case 'removeContactFromList':
                return $this->brevoService->removeContactFromList($this->configs, $this->email);
        }
    }

    private function executeBrevoAction(): array
    {
        $this->setNodeInfoProperties();
        $this->brevoService = new BrevoService(self::BASE_URL, $this->header);

        return $this->executeAction();
    }
}
