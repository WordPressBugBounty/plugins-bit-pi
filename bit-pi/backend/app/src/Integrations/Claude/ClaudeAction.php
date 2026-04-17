<?php

namespace BitApps\Pi\src\Integrations\Claude;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

class ClaudeAction implements ActionInterface
{
    private NodeInfoProvider $nodeInfoProvider;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeClaudeAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeMachine($machineSlug, $data, $apiKey)
    {
        $header = [
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json'
        ];
        $ClaudeHelperObj = new ClaudeHelper($header);

        switch ($machineSlug) {
            case 'askClaude':
                return $ClaudeHelperObj->askClaude($data);

                break;

            case 'createCompletion':
                $messages = $this->nodeInfoProvider->getFieldMapRepeaters('message-field-properties.value', false, false);

                return $ClaudeHelperObj->createCompletion($messages, $data);

                break;
        }
    }

    private function executeClaudeAction()
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
