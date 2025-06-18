<?php

namespace BitApps\Pi\src\Integrations\MailBluster;

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!\defined('ABSPATH')) {
    exit;
}


class MailBlusterAction implements ActionInterface
{
    public const BASE_URL = 'https://api.mailbluster.com/api/';

    private $nodeInfoProvider;

    private $MailBlusterService;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeMailBlusterAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeMailBlusterAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();
        $data = $this->nodeInfoProvider->getFieldMapData();
        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');

        if (empty($connectionId)) {
            return [
                'response'    => ['message' => __('No connection selected!', 'bit-pi')],
                'payload'     => [],
                'status_code' => 400
            ];
        }

        $mailBlusterFields = $this->nodeInfoProvider->getFieldMapRepeaters('field-map.value', false, true, 'mailBlusterField', 'value');

        $this->MailBlusterService = new MailBlusterService(static::BASE_URL, $connectionId);

        if ($machineSlug === 'addLead') {
            return $this->MailBlusterService->addLead($mailBlusterFields, $data);
        }
    }
}
