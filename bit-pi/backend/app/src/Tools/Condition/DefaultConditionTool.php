<?php

namespace BitApps\Pi\src\Tools\Condition;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Model\FlowLog;
use BitApps\Pi\src\DTO\FlowToolResponseDTO;
use BitApps\Pi\src\Log\LogHandler;
use BitApps\Pi\src\Tools\FlowToolsFactory;

class DefaultConditionTool
{
    private const MACHINE_SLUG = 'default-condition';

    private $nodeInfo;

    public function __construct($currentNode)
    {
        $this->nodeInfo = $currentNode;
    }

    public function execute()
    {
        $previousNodeId = $this->nodeInfo->previous;

        $nodeId = $this->nodeInfo->id;

        $conditionStatus = $this->checkAnyConditionMet($previousNodeId);

        if ($conditionStatus) {
            return FlowToolResponseDTO::create(
                '',
                [],
                [],
                '',
                [],
                true,
                $nodeId,
                false
            );
        }

        $details = [
            'app_slug'     => FlowToolsFactory::APP_SLUG,
            'machine_slug' => self::MACHINE_SLUG,
            'title'        => 'No Condition Matched',
        ];

        $outputData = [
            'condition' => 'true',
        ];

        return FlowToolResponseDTO::create(
            FlowLog::STATUS['SUCCESS'],
            [],
            $outputData,
            'Default condition met',
            $details,
            $conditionStatus,
            $nodeId
        );
    }

    private function checkAnyConditionMet($parentNodeId): bool
    {
        $logs = LogHandler::getLogs();

        foreach ($logs as $log) {
            if (strpos($log['node_id'], $parentNodeId) === 0 && $log['messages'] === 'Condition met') {
                return true;
            }
        }

        return false;
    }
}
