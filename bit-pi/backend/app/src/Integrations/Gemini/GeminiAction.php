<?php

namespace BitApps\Pi\src\Integrations\Gemini;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

class GeminiAction implements ActionInterface
{
    private NodeInfoProvider $nodeInfoProvider;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeGeminiAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeGeminiAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();

        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');
        $messages = $this->nodeInfoProvider->getFieldMapRepeaters('message-field-properties.value', false, false);
        $safetySettings = $this->nodeInfoProvider->getFieldMapRepeaters('safety-settings.value', false, false);
        $data = $this->nodeInfoProvider->getFieldMapData();

        $tokenAuthorization = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::API_KEY,
            $connectionId
        );

        $apiKey = $tokenAuthorization->getAccessToken();
        $header = [
            'content-type' => 'application/json'
        ];

        $geminiHelperObj = new GeminiHelper($header);

        switch ($machineSlug) {
            case 'askGemini':
                return $geminiHelperObj->askGemini($data, $apiKey);

                break;

            case 'createCompletion':
                return $geminiHelperObj->generatetext($messages, $data, $apiKey);

            case 'generateImage':
                $model = $this->nodeInfoProvider->getFieldMapConfigs('model-id.value');

                return $geminiHelperObj->generateImage($data, $apiKey, $model, $safetySettings);

                break;
        }
    }
}
