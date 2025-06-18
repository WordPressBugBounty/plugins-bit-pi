<?php

namespace BitApps\Pi\src\Integrations\GetGist;

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!\defined('ABSPATH')) {
    exit;
}


class GetGistAction implements ActionInterface
{
    public const BASE_URL = 'https://api.getgist.com';

    private $nodeInfoProvider;

    private $GetGistService;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeGetGistAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeGetGistAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();

        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');

        $overRideExistingEmail = $this->nodeInfoProvider->getFieldMapConfigs('override-existing.value');

        $tagId = $this->nodeInfoProvider->getFieldMapConfigs('tag-id.value');

        $repeaters = $this->nodeInfoProvider->getFieldMapRepeaters('contact-data.value', false, false);

        $this->GetGistService = new GetGistService(static::BASE_URL, $connectionId);

        if ($machineSlug === 'createContact') {
            return $this->GetGistService->createContact($repeaters, $tagId, $overRideExistingEmail);
        }
    }
}
