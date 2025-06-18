<?php

namespace BitApps\Pi\src\Integrations\SystemIo;

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!\defined('ABSPATH')) {
    exit;
}


class SystemIoAction implements ActionInterface
{
    public const BASE_URL = 'https://api.systeme.io/api';

    private $nodeInfoProvider;

    private $systemIoService;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeSystemIoAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeSystemIoAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();

        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');

        $tagId = $this->nodeInfoProvider->getFieldMapConfigs('tag-id.value');

        $repeaters = $this->nodeInfoProvider->getFieldMapRepeaters('contact-data.value', false, false);

        $this->systemIoService = new SystemIoService(static::BASE_URL, $connectionId);

        if ($machineSlug === 'createContact') {
            return $this->systemIoService->createContact($repeaters, $tagId);
        }
    }
}
