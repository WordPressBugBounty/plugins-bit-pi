<?php

namespace BitApps\Pi\src\Integrations\BitConnect;

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Integrations\BitConnect\helper\BitConnectHelper;
use BitApps\Pi\src\Integrations\IntegrationHelper;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!defined('ABSPATH')) {
    exit;
}

class BitConnectAction implements ActionInterface
{
    private $nodeInfoProvider;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeBitConnectAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'] ?? 200,
            $executedNodeAction['payload'] ?? [],
            $executedNodeAction['response'] ?? []
        );
    }

    private function executeBitConnectAction()
    {
        if (!BitConnectHelper::isActionsAvailable()) {
            return [
                'status_code' => 400,
                'payload'     => [],
                'response'    => __('Bit Connect Pro is not active or has no active licence. Activate Bit Connect Pro and its licence.', 'bit-pi'),
            ];
        }

        $machineSlug = $this->nodeInfoProvider->getMachineSlug();

        $serviceInstance = IntegrationHelper::getServiceInstanceByMachineSlug($machineSlug, $this->nodeInfoProvider);

        if (!$serviceInstance) {
            return [
                'status_code' => 400,
                'payload'     => [],
                'response'    => \sprintf(
                    // translators: %s: action method name
                    __('Bit Connect action "%s" is not registered.', 'bit-pi'),
                    $machineSlug
                ),
            ];
        }

        return $serviceInstance->{$machineSlug}();
    }
}
