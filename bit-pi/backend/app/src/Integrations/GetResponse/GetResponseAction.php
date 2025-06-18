<?php

namespace BitApps\Pi\src\Integrations\GetResponse;

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!\defined('ABSPATH')) {
    exit;
}


class GetResponseAction implements ActionInterface
{
    public const BASE_URL = 'https://api.getresponse.com/v3/';

    private $nodeInfoProvider;

    private $getResponseService;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeGetResponseAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeGetResponseAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();
        $data = $this->nodeInfoProvider->getFieldMapData();
        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');
        $listId = $this->nodeInfoProvider->getFieldMapConfigs('list-id.value');
        $getResponseFields = $this->nodeInfoProvider->getFieldMapRepeaters('field-map.value', false, true, 'getResponseField', 'value');

        $this->getResponseService = new GetResponseService(static::BASE_URL, $connectionId);

        if ($machineSlug === 'addContact') {
            return $this->getResponseService->addContact($getResponseFields, $listId, $data);
        }
    }
}
