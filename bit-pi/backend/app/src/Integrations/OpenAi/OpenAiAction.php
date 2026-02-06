<?php

namespace BitApps\Pi\src\Integrations\OpenAi;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Helpers\MixInputHandler;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Integrations\OpenAi\helpers\OpenAiActionHandler;
use BitApps\Pi\src\Interfaces\ActionInterface;

class OpenAiAction implements ActionInterface
{
    private const BASE_URL = 'https://api.openai.com/v1';

    private NodeInfoProvider $nodeInfoProvider;

    private OpenAiService $openAiService;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeOpenAiAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeOpenAiAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();
        $configs = $this->nodeInfoProvider->getFieldMapConfigs();
        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');
        $batchId = $this->nodeInfoProvider->getFieldMapConfigs('batch-id.value');
        $inputText = $this->nodeInfoProvider->getFieldMapRepeaters('input-list.value', false, false);
        $stopSequence = $this->nodeInfoProvider->getFieldMapRepeaters('stop-sequences-list.value', false, false);
        $optionalFields = $this->nodeInfoProvider->getFieldMapRepeaters('optional-field-list.value', false, false);
        $messageList = $this->nodeInfoProvider->getFieldMapRepeaters('messages-list.value', false, false);
        $inputFormat = $this->nodeInfoProvider->getFieldMapConfigs('input-format.value');
        $fieldMapData = $this->nodeInfoProvider->getFieldMapData();
        $batchLimit = $this->nodeInfoProvider->getFieldMapConfigs('batch-limit.value');
        $memoryKey = $this->nodeInfoProvider->getFieldMapConfigs('memory-key.value');
        $contextLength = $this->nodeInfoProvider->getFieldMapConfigs('context-length.value');
        $batchLimit = MixInputHandler::replaceMixTagValue($batchLimit);
        $memoryKey = MixInputHandler::replaceMixTagValue($memoryKey);
        $contextLength = MixInputHandler::replaceMixTagValue($contextLength);
        $fieldMapData = OpenAiActionHandler::handleConditions($fieldMapData, $stopSequence, $messageList, $inputFormat, $inputText, $optionalFields);

        if (!empty($optionalFields)) {
            $fieldMapData = OpenAiActionHandler::castFieldsIfExist($fieldMapData);
        }

        $batchLimit = (int) $batchLimit;

        $tokenAuthorization = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::API_KEY,
            $connectionId
        );

        $apiKey = $tokenAuthorization->getAccessToken();

        $header = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $apiKey
        ];

        $this->openAiService = new OpenAiService(self::BASE_URL, $header);

        return OpenAiActionHandler::executeAction(
            $machineSlug,
            $this->openAiService,
            $batchLimit,
            $batchId,
            $fieldMapData,
            $configs,
            $memoryKey,
            $contextLength
        );
    }
}
