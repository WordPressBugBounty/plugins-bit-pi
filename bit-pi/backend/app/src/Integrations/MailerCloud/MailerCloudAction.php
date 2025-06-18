<?php

namespace BitApps\Pi\src\Integrations\MailerCloud;

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!\defined('ABSPATH')) {
    exit;
}


class MailerCloudAction implements ActionInterface
{
    public const BASE_URL = 'https://cloudapi.mailercloud.com/v1';

    private $nodeInfoProvider;

    private $mailerCloudService;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeMailerCloudAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeMailerCloudAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();

        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');

        $listId = $this->nodeInfoProvider->getFieldMapConfigs('list-id.value');

        $tagId = $this->nodeInfoProvider->getFieldMapConfigs('tag-id.value');

        $repeaters = $this->nodeInfoProvider->getFieldMapRepeaters('contact-data.value', false, false);

        $this->mailerCloudService = new MailerCloudService(static::BASE_URL, $connectionId);

        if ($machineSlug === 'createContact') {
            return $this->mailerCloudService->createContact($repeaters, $tagId, $listId);
        }
    }
}
