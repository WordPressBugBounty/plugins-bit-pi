<?php

namespace BitApps\Pi\src\Integrations\Woodpecker;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

class WoodpeckerAction implements ActionInterface
{
    public const BASE_URL = 'https://api.woodpecker.co/rest/v1';

    private NodeInfoProvider $nodeInfoProvider;

    private WoodpeckerProspect $woodpeckerProspect;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeWoodpeckerAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeWoodpeckerAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();
        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');
        $campaignId = $this->nodeInfoProvider->getFieldMapConfigs('campaign-id.value');
        $prospects = $this->nodeInfoProvider->getFieldMapRepeaters('prospects-list.value', false, true, 'woodpeckerField');
        $prospectId = $this->nodeInfoProvider->getFieldMapConfigs('prospect-id.value');
        $searchStatus = $this->nodeInfoProvider->getFieldMapConfigs('search-status.value');

        if (!empty($prospects) && !isset($prospects[0])) {
            $prospects = [$prospects];
        }

        $newProspectData = [
            'update'    => false,
            'prospects' => $prospects
        ];

        $updateProspectData = [
            'update'    => true,
            'prospects' => $prospects
        ];

        $prospectToCampaignData = [
            'campaign' => [
                'campaign_id' => (int) $campaignId
            ],
            'update'    => true,
            'prospects' => $prospects
        ];

        $tokenAuthorization = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::API_KEY,
            $connectionId
        );

        $apiKey = $tokenAuthorization->getAccessToken();

        $header = [
            'X-API-KEY'    => $apiKey,
            'Content-Type' => 'application/json'
        ];

        $this->woodpeckerProspect = new WoodpeckerProspect(self::BASE_URL, $header);
        if ($machineSlug === 'addProspect') {
            return $this->woodpeckerProspect->addProspectsToList($newProspectData);
        }
        if ($machineSlug === 'addProspectToCampaign') {
            return $this->woodpeckerProspect->addProspectsToCampaign($prospectToCampaignData);
        }
        if ($machineSlug === 'updateProspect') {
            return $this->woodpeckerProspect->addProspectsToList($updateProspectData);
        }
        if ($machineSlug === 'deleteProspect') {
            return $this->woodpeckerProspect->deleteProspect($prospectId);
        }

        if ($machineSlug === 'searchProspect') {
            return $this->woodpeckerProspect->searchProspect($searchStatus);
        }
    }
}
