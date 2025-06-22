<?php

namespace BitApps\Pi\src\Integrations\HubSpot;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

class HubSpotAction implements ActionInterface
{
    private const BASE_URL = 'https://api.hubapi.com';

    private NodeInfoProvider $nodeInfoProvider;

    private HubSpotService $hubSpotService;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeHubSpotAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function setNodeInfoProperties()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();
        $configs = $this->nodeInfoProvider->getFieldMapConfigs();
        $emailList = $this->nodeInfoProvider->getFieldMapRepeaters('email-list.value', false, false);
        $contactData = $this->nodeInfoProvider->getFieldMapRepeaters('contact-field-list.value', false, true, 'contactFields');
        $companyData = $this->nodeInfoProvider->getFieldMapRepeaters('company-field-list.value', false, true, 'companyFields');
        $dealList = $this->nodeInfoProvider->getFieldMapRepeaters('deal-list.value', false, true, 'hubSpotFields');
        $fieldMapData = $this->nodeInfoProvider->getFieldMapData();

        $email = array_column($emailList, 'email');

        $tokenAuthorization = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::API_KEY,
            $configs['connection-id']
        );

        $apiKey = $tokenAuthorization->getAccessToken();

        $header = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $apiKey
        ];

        return [
            'machineSlug'  => $machineSlug,
            'configs'      => $configs,
            'fieldMapData' => $fieldMapData,
            'email'        => $email,
            'header'       => $header,
            'contactData'  => $contactData,
            'companyData'  => $companyData,
            'dealList'     => $dealList
        ];
    }

    private function executeAction(array $hubSpotData)
    {
        switch ($hubSpotData['machineSlug']) {
            case 'createOrUpdateContact':
                $fieldMapData = ['properties' => array_merge($hubSpotData['fieldMapData'], $hubSpotData['contactData'])];

                return $this->hubSpotService->createContact($hubSpotData['configs']['update-contact']['value'], $fieldMapData);

            case 'createOrUpdateCompany':
                $fieldMapData = ['properties' => array_merge($hubSpotData['fieldMapData'], $hubSpotData['companyData'])];

                return $this->hubSpotService->createCompany($hubSpotData['configs']['company-id']['value'], $hubSpotData['configs']['update-company']['value'], $fieldMapData);

            case 'createOrUpdateDeal':
                $fieldMapData = empty($dealList) ? $hubSpotData['fieldMapData'] : array_merge($hubSpotData['fieldMapData'], $hubSpotData['dealList']);
                $fieldMapData = ['properties' => $fieldMapData];

                return $this->hubSpotService->createDeal($hubSpotData['configs']['deal-id']['value'], $hubSpotData['configs']['update-deal']['value'], $fieldMapData);

            case 'addContactsToList':
                return $this->hubSpotService->addContactToList($hubSpotData['configs']['list-id']['value'], $hubSpotData['email']);

            case 'removeContactFromList':
                return $this->hubSpotService->removeContactFromList($hubSpotData['configs']['list-id']['value'], $hubSpotData['email']);
        }
    }

    private function executeHubSpotAction(): array
    {
        $hubSpotData = $this->setNodeInfoProperties();
        $this->hubSpotService = new HubSpotService(self::BASE_URL, $hubSpotData['header']);

        return $this->executeAction($hubSpotData);
    }
}
