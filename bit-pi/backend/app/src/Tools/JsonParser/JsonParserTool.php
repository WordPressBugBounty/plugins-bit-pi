<?php

namespace BitApps\Pi\src\Tools\JsonParser;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Helpers\MixInputHandler;
use BitApps\Pi\Model\FlowLog;
use BitApps\Pi\src\DTO\FlowToolResponseDTO;
use BitApps\Pi\src\Flow\GlobalNodeVariables;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Tools\FlowToolsFactory;

class JsonParserTool
{
    public const MACHINE_SLUG = 'jsonParser';

    protected $nodeInfoProvider;

    private $flowHistoryId;

    public function __construct(NodeInfoProvider $nodeInfoProvider, $flowHistory)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
        $this->flowHistoryId = $flowHistory;
    }

    public function execute()
    {
        $flowId = $this->nodeInfoProvider->getFlowId();

        $nodeId = $this->nodeInfoProvider->getNodeId();

        $nodeVariableInstance = GlobalNodeVariables::getInstance($this->flowHistoryId, $flowId);

        $jsonStringValue = $this->nodeInfoProvider->getData()['jsonParser']['value'] ?? [];

        $parsedInput = MixInputHandler::replaceMixTagValue($jsonStringValue);

        $details = [
            'app_slug'     => FlowToolsFactory::APP_SLUG,
            'machine_slug' => self::MACHINE_SLUG,
        ];

        $errorMessage = '';

        if (!\is_string($parsedInput) || trim($parsedInput) === '') {
            $errorMessage = 'JSON string is empty';
        }

        $jsonDecoded = $errorMessage ? false : JSON::is($parsedInput, true);

        if (!$errorMessage && $jsonDecoded === false) {
            $errorMessage = 'Invalid JSON string';
        }

        if ($errorMessage) {
            return FlowToolResponseDTO::create(
                FlowLog::STATUS['ERROR'],
                $jsonStringValue,
                [
                    'error' => $errorMessage
                ],
                $errorMessage,
                $details,
            );
        }

        $nodeVariableInstance->setVariables($nodeId, $jsonDecoded);

        $nodeVariableInstance->setNodeResponse($nodeId, $jsonDecoded);

        return FlowToolResponseDTO::create(
            FlowLog::STATUS['SUCCESS'],
            $jsonStringValue,
            $jsonDecoded,
            'Json parsed successfully',
            $details,
        );
    }
}
