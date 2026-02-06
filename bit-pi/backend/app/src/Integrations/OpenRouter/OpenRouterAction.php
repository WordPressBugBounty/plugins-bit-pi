<?php

namespace BitApps\Pi\src\Integrations\OpenRouter;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\Helpers\MixInputHandler;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

class OpenRouterAction implements ActionInterface
{
    private NodeInfoProvider $nodeInfoProvider;

    private OpenRouterService $openRouterService;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeIntegrationAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeMachine(string $machineSlug, array $configs, array $fieldMapData, array $messagesList)
    {
        switch ($machineSlug) {
            case 'createChatCompletion':
                $fieldMapData['messages'] = $messagesList;
                $isMemoryEnabled = $configs['memory-key-switch']['value'] ?? null;
                $memoryKeyMixInput = $this->nodeInfoProvider->getFieldMapConfigs('memory-key.value');
                $memoryKey = MixInputHandler::replaceMixTagValue($memoryKeyMixInput);
                $contextLength = $this->nodeInfoProvider->getFieldMapConfigs('context-length.value');
                $contextLength = MixInputHandler::replaceMixTagValue($contextLength);

                return $this->openRouterService->createChatCompletion($fieldMapData, $isMemoryEnabled, $memoryKey, $contextLength);

            case 'listModels':
                return $this->openRouterService->listModels();

            case 'fetchCredits':
                return $this->openRouterService->fetchCredits();
        }
    }

    private function executeIntegrationAction(): array
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();
        $configs = $this->nodeInfoProvider->getFieldMapConfigs();
        $fieldMapData = $this->nodeInfoProvider->getFieldMapData();
        $messagesList = $this->nodeInfoProvider->getFieldMapRepeaters('messages-list.value', false, false);

        $token = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::API_KEY,
            $configs['connection-id']
        )->getAccessToken();

        $httpClient = new HttpClient(
            [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ],
            ]
        );

        $this->openRouterService = new OpenRouterService($httpClient);

        return $this->executeMachine($machineSlug, $configs, $fieldMapData, $messagesList);
    }
}
