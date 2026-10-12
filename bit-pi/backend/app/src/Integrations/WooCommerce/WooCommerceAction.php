<?php

namespace BitApps\Pi\src\Integrations\WooCommerce;

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Integrations\IntegrationHelper;
use BitApps\Pi\src\Integrations\WooCommerce\helper\WooCommerceHelper;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!defined('ABSPATH')) {
    exit;
}

class WooCommerceAction implements ActionInterface
{
    private $nodeInfoProvider;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeWooCommerceAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'] ?? 200,
            $executedNodeAction['payload'] ?? [],
            $executedNodeAction['response'] ?? []
        );
    }

    private function executeWooCommerceAction()
    {
        if (!WooCommerceHelper::isPluginInstalled()) {
            return [
                'status_code' => 400,
                'payload'     => [],
                'response'    => __('WooCommerce plugin is not installed or activated', 'bit-pi'),
            ];
        }

        $machineSlug = $this->nodeInfoProvider->getMachineSlug();

        $serviceInstance = IntegrationHelper::getServiceInstanceByMachineSlug($machineSlug, $this->nodeInfoProvider);

        if (!$serviceInstance) {
            return [
                'status_code' => 400,
                'payload'     => [],
                'response'    => __('WooCommerce service not found for the given machine slug.', 'bit-pi'),
            ];
        }

        return $serviceInstance->{$machineSlug}();
    }
}
