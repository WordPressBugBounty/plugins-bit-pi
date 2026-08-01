<?php

namespace BitApps\Pi\src\Integrations\BitCrm;

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Integrations\IntegrationHelper;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!defined('ABSPATH')) {
    exit;
}

class BitCrmAction implements ActionInterface
{
    private $nodeInfoProvider;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeBitCrmAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'] ?? 200,
            $executedNodeAction['payload'] ?? [],
            $executedNodeAction['response'] ?? []
        );
    }

    private function executeBitCrmAction()
    {
        if (!BitCrmHelper::isActive()) {
            return [
                'status_code' => 400,
                'payload'     => [],
                'response'    => __('Bit CRM is not installed or activated', 'bit-pi'),
            ];
        }

        $machineSlug = $this->nodeInfoProvider->getMachineSlug();
        if (empty($machineSlug)) {
            return [
                'status_code' => 400,
                'payload'     => [],
                'response'    => __('Action method is required.', 'bit-pi'),
            ];
        }

        $serviceInstance = IntegrationHelper::getServiceInstanceByMachineSlug($machineSlug, $this->nodeInfoProvider);
        if (!$serviceInstance) {
            return [
                'status_code' => 400,
                'payload'     => [],
                'response'    => \sprintf(
                    // translators: %s: action method name
                    __('Bit CRM action "%s" is not registered.', 'bit-pi'),
                    $machineSlug
                ),
            ];
        }

        return $serviceInstance->{$machineSlug}();
    }
}
