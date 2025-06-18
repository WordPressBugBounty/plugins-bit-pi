<?php

namespace BitApps\Pi\src\Integrations\GoHighLevel;

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!\defined('ABSPATH')) {
    exit;
}


class GoHighLevelAction implements ActionInterface
{
    public const BASE_URL = 'https://rest.gohighlevel.com/v1/';

    private $nodeInfoProvider;

    private $goHighLevelService;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeGoHighLevelAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeGoHighLevelAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();
        $data = $this->nodeInfoProvider->getFieldMapData();
        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');
        $contactId = $this->nodeInfoProvider->getFieldMapConfigs('contact-id.value');
        $goHighLevelFields = $this->nodeInfoProvider->getFieldMapRepeaters('field-map.value', false, true, 'goHighLevelField', 'value');

        $this->goHighLevelService = new GoHighLevelService(static::BASE_URL, $connectionId);
        if ($machineSlug === 'addContact') {
            return $this->goHighLevelService->addContact($goHighLevelFields, $data);
        }

        if ($machineSlug === 'updateContact') {
            return $this->goHighLevelService->updateContact($contactId, $goHighLevelFields, $data);
        }
    }
}
