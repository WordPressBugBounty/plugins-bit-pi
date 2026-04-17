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

        $jsonDecoded = JSON::is(MixInputHandler::replaceMixTagValue($jsonStringValue), true);

        $nodeVariableInstance->setVariables($nodeId, $jsonDecoded ?? []);

        $nodeVariableInstance->setNodeResponse($nodeId, $jsonDecoded ?? []);

        $details = [
            'app_slug'     => FlowToolsFactory::APP_SLUG,
            'machine_slug' => self::MACHINE_SLUG,
        ];

        $message = $jsonDecoded
            ? 'Json parsed successfully'
            : 'Json parsing failed';

        $status = $jsonDecoded ? FlowLog::STATUS['SUCCESS'] : FlowLog::STATUS['ERROR'];

        return FlowToolResponseDTO::create(
            $status,
            $jsonStringValue,
            $jsonDecoded ? $jsonDecoded : [],
            $message,
            $details,
        );
    }
}
