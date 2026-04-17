<?php

namespace BitApps\Pi\src\Integrations\DeepSeek;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

class DeepSeekAction implements ActionInterface
{
    private NodeInfoProvider $nodeInfoProvider;

    private DeepSeekHelper $deepSeekApi;

    private $machineSlug;

    private $data;

    private $tokenAuthorization;

    private $apiKey;

    private $messages;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeDeepSeekAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeMachine(): array
    {
        switch ($this->machineSlug) {
            case 'createChatCompletion':
                return $this->deepSeekApi->createChatCompletion($this->messages, $this->data);

                break;

            case 'listModels':
                return $this->deepSeekApi->listModels();

                break;

            case 'getUserBalance':
                return $this->deepSeekApi->getUserBalance();

                break;
        }

        return [];
    }

    private function setNodeInfoProperties()
    {
        $this->machineSlug = $this->nodeInfoProvider->getMachineSlug();
        $this->data = $this->nodeInfoProvider->getFieldMapData();
        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');
        $this->messages = $this->nodeInfoProvider->getFieldMapRepeaters('message-field-properties.value', false, false);

        $this->tokenAuthorization = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::API_KEY,
            $connectionId
        );
        $this->apiKey = $this->tokenAuthorization->getAccessToken();
    }

    private function executeDeepSeekAction(): array
    {
        $this->setNodeInfoProperties();
        $this->deepSeekApi = new DeepSeekHelper($this->tokenAuthorization, $this->apiKey);

        return $this->executeMachine();
    }
}
