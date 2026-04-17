<?php

namespace BitApps\Pi\src\Integrations\ElevenLabs;

use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!defined('ABSPATH')) {
    exit;
}

class ElevenLabsAction implements ActionInterface
{
    private NodeInfoProvider $nodeInfoProvider;

    private ElevenLabsService $elevenLabsService;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeElevenLabsAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeMachine($machineSlug, $fieldMapData, $configs)
    {
        $voiceId = $configs['voice-id']['value'] ?? null;
        $agentId = $fieldMapData['agentId'] ?? null;

        switch ($machineSlug) {
            case 'textToSpeech':
                return $this->elevenLabsService->textToSpeech($fieldMapData, $voiceId);

            case 'speechToText':
                return $this->elevenLabsService->speechToText($configs['model-id']['value'] ?? null, $fieldMapData);

            case 'createAgent':
                return $this->elevenLabsService->createAgent($fieldMapData);

            case 'getAgent':
                return $this->elevenLabsService->getAgent($agentId);

            case 'deleteAgent':
                return $this->elevenLabsService->deleteAgent($agentId);

            case 'listAgents':
                return $this->elevenLabsService->listAgents($fieldMapData);

            case 'listVoices':
                return $this->elevenLabsService->listVoices();

            case 'getVoice':
                return $this->elevenLabsService->getVoice($voiceId);

            case 'deleteVoice':
                return $this->elevenLabsService->deleteVoice($fieldMapData['voiceId'] ?? null);
        }
    }

    private function executeElevenLabsAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();
        $configs = $this->nodeInfoProvider->getFieldMapConfigs();
        $fieldMapData = $this->nodeInfoProvider->getFieldMapData();

        $apiKey = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::API_KEY,
            $configs['connection-id']
        )->getAccessToken();

        $httpClient = new HttpClient(
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'xi-api-key'   => $apiKey,
                ],
            ]
        );

        $this->elevenLabsService = new ElevenLabsService($httpClient);

        return $this->executeMachine($machineSlug, $fieldMapData, $configs);
    }
}
