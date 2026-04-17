<?php

namespace BitApps\Pi\src\Tools\Repeater;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Model\FlowLog;
use BitApps\Pi\src\DTO\FlowToolResponseDTO;
use BitApps\Pi\src\Flow\GlobalNodeVariables;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Tools\FlowToolsFactory;

class RepeaterTool
{
    private const MACHINE_SLUG = 'repeater';

    protected $nodeInfoProvider;

    private $flowHistoryId;

    public function __construct(NodeInfoProvider $nodeInfoProvider, $flowHistory = null)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
        $this->flowHistoryId = $flowHistory;
    }

    public function execute()
    {
        $flowId = $this->nodeInfoProvider->getFlowId();

        $nodeId = $this->nodeInfoProvider->getNodeId();

        $nodeVariableInstance = GlobalNodeVariables::getInstance($this->flowHistoryId, $flowId);

        $repeaterConfig = $this->nodeInfoProvider->getData()['repeater'] ?? [];

        $initialValue = $repeaterConfig['initial_value'] ?? 1;

        $repeat = $repeaterConfig['repeat'] ?? 1;

        $step = $repeaterConfig['step'] ?? 1;

        $inputData = [
            'initial_value' => $initialValue,
            'repeat'        => $repeat,
            'step'          => $step,
        ];

        $outputData = [];

        for ($i = 0; $i < $repeat; ++$i) {
            $outputData[] = ['i' => $initialValue + $i * $step];
        }
        $nodeVariableInstance->setVariables($nodeId, $outputData[0]);

        $nodeVariableInstance->setNodeResponse($nodeId, $outputData);

        $details = [
            'app_slug'     => FlowToolsFactory::APP_SLUG,
            'machine_slug' => self::MACHINE_SLUG,
        ];


        return FlowToolResponseDTO::create(
            FlowLog::STATUS['SUCCESS'],
            $inputData,
            $outputData,
            'Repeater executed successfully',
            $details,
        );
    }
}
