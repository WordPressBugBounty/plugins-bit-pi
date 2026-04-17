<?php

namespace BitApps\Pi\src\Integrations\Perplexity;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

class PerplexityAction implements ActionInterface
{
    private NodeInfoProvider $nodeInfoProvider;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executePerplexityAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeMachine($machineSlug, $data, $apiKey)
    {
        $header = [
            'accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $apiKey,
            'content-type'  => 'application/json'
        ];
        $perplexityHelperObj = new PerplexityHelper($header);

        switch ($machineSlug) {
            case 'askPerplexity':
                return $perplexityHelperObj->askPerplexity($data);

                break;

            case 'createCompletion':
                $messages = $this->nodeInfoProvider->getFieldMapRepeaters('message-field-properties.value', false, false);

                return $perplexityHelperObj->createCompletion($messages, $data);

                break;
        }
    }

    private function executePerplexityAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();
        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');
        $data = $this->nodeInfoProvider->getFieldMapData();

        $tokenAuthorization = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::API_KEY,
            $connectionId
        );
        $apiKey = $tokenAuthorization->getAccessToken();

        return $this->executeMachine($machineSlug, $data, $apiKey);
    }
}
