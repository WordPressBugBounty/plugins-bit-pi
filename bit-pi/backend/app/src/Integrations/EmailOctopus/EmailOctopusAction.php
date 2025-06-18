<?php

namespace BitApps\Pi\src\Integrations\EmailOctopus;

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!\defined('ABSPATH')) {
    exit;
}


class EmailOctopusAction implements ActionInterface
{
    public const BASE_URL = 'https://api.emailoctopus.com/lists/';

    private $nodeInfoProvider;

    private $emailOctopusService;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeEmailOctopusAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeEmailOctopusAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();
        $data = $this->nodeInfoProvider->getFieldMapData();
        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');
        $listId = $this->nodeInfoProvider->getFieldMapConfigs('list-id.value');
        $emailOctopusFields = $this->nodeInfoProvider->getFieldMapRepeaters('field-map.value', false, true, 'emailOctopusField', 'value');

        $this->emailOctopusService = new EmailOctopusService(static::BASE_URL, $connectionId);

        if ($machineSlug === 'addContact') {
            return $this->emailOctopusService->addContact($emailOctopusFields, $listId, $data);
        }
    }
}
