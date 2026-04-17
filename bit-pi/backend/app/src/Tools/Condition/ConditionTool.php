<?php

namespace BitApps\Pi\src\Tools\Condition;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Helpers\Node;
use BitApps\Pi\Model\FlowLog;
use BitApps\Pi\src\DTO\FlowToolResponseDTO;
use BitApps\Pi\src\Flow\GlobalNodes;
use BitApps\Pi\src\Tools\FlowToolsFactory;

class ConditionTool
{
    public const MACHINE_SLUG = 'condition';

    private $nodeInfo;

    public function __construct($currentNodeInfo)
    {
        $this->nodeInfo = $currentNodeInfo;
    }

    public function execute()
    {
        $nodeId = $this->nodeInfo->id;

        $nodeInstance = GlobalNodes::getInstance(explode('-', $nodeId)[0]);

        $currentNodeInfo = Node::getNodeInfoById($this->nodeInfo->previous, $nodeInstance->getAllNodeData());

        $conditionData = Node::getConditionsByNodeId($nodeId, $currentNodeInfo);

        $startTime = microtime(true);

        $result = ConditionalLogic::conditionStatus($conditionData['condition']);

        $conditionStatus = $result['condition_status'];

        $inputData = $result['condition_evaluation'];

        $endTime = microtime(true);

        $details = [
            'duration'     => number_format($endTime - $startTime, 2),
            'data_size'    => number_format(mb_strlen(JSON::encode($conditionData['condition']), '8bit') / 1024, 2),
            'app_slug'     => FlowToolsFactory::APP_SLUG,
            'machine_slug' => self::MACHINE_SLUG,
            'title'        => $conditionData['title'] ?? '',
        ];

        $outputData = [
            'condition' => $conditionStatus ? 'true' : 'false',
        ];


        $message = $conditionStatus === false ? 'Condition not met' : 'Condition met';

        return FlowToolResponseDTO::create(
            FlowLog::STATUS['SUCCESS'],
            $inputData,
            $outputData,
            $message,
            $details,
            !$conditionStatus,
            $nodeId
        );
    }
}
