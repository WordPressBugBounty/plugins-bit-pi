<?php

namespace BitApps\Pi\src\Integrations\Groq;

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

class GroqAction implements ActionInterface
{
    private NodeInfoProvider $nodeInfoProvider;

    private GroqService $groqService;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeGroqAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeMachine($machineSlug, $data, $configs)
    {
        switch ($machineSlug) {
            case 'createChatCompletion':
                $messages = $this->nodeInfoProvider->getFieldMapRepeaters('messages-list.value', false, false);
                $memoryKey = $configs['memory-key']['value'] ?? '';
                $contextLength = $configs['context-length']['value'] ?? '';
                $isMemoryEnabled = $configs['memory-key-switch']['value'] ?? false;

                $memoryKey = MixInputHandler::replaceMixTagValue($memoryKey);
                $contextLength = MixInputHandler::replaceMixTagValue($contextLength);

                return $this->groqService->createChatCompletion(
                    $messages,
                    $data,
                    $isMemoryEnabled,
                    $memoryKey,
                    (int) $contextLength
                );

            case 'transcribeAudio':
                return $this->groqService->transcribeAudio($data);

            case 'translateAudio':
                return $this->groqService->translateAudio($data);

            case 'analyzeImage':
                return $this->groqService->analyzeImage($data);
        }
    }

    private function executeGroqAction(): array
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();
        $data = $this->nodeInfoProvider->getFieldMapData();
        $configs = $this->nodeInfoProvider->getFieldMapConfigs();
        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');

        $tokenAuthorization = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::API_KEY,
            $connectionId
        );

        $apiKey = $tokenAuthorization->getAccessToken();

        $httpClient = new HttpClient(
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ],
            ]
        );

        $this->groqService = new GroqService($httpClient);

        return $this->executeMachine($machineSlug, $data, $configs);
    }
}
