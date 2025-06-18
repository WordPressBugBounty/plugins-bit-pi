<?php

namespace BitApps\Pi\src\Integrations\Selzy;

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!\defined('ABSPATH')) {
    exit;
}


class SelzyAction implements ActionInterface
{
    public const BASE_URL = 'https://api.selzy.com/en/api';

    private $nodeInfoProvider;

    private $selzyService;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeSelzyAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeSelzyAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();

        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');

        $listId = $this->nodeInfoProvider->getFieldMapConfigs('list-id.value');

        $tagId = $this->nodeInfoProvider->getFieldMapConfigs('tag-id.value');

        $overrideExisting = $this->nodeInfoProvider->getFieldMapConfigs('override-existing.value');

        $doubleOptin = $this->nodeInfoProvider->getFieldMapConfigs('double-optin.value');

        $repeaters = $this->nodeInfoProvider->getFieldMapRepeaters('contact-data.value', false, false);

        $this->selzyService = new SelzyService(static::BASE_URL, $connectionId);

        if ($machineSlug === 'createContact') {
            return $this->selzyService->createContact($repeaters, $tagId, $listId, $overrideExisting, $doubleOptin);
        }
    }
}
