<?php

namespace BitApps\Pi\src\Flow;

use BitApps\Pi\Model\FlowLog;
use BitApps\Pi\Model\FlowNode;

if (!defined('ABSPATH')) {
    exit;
}


class GlobalNodeVariables
{
    private static $instance;

    private $nodeVariables = [];

    private $nodeResponseData = [];

    private $nodeIndexPosition = [];

    private function __construct($flowHistoryId = null, $flowId = null)
    {
        $this->fetchNodeVariables($flowId);
        $this->fetchNodeResponse($flowHistoryId);
    }

    public static function getInstance($flowHistoryId = null, $flowId = null)
    {
        if (self::$instance === null) {
            self::$instance = new self($flowHistoryId, $flowId);
        }

        return self::$instance;
    }

    public function setNodeResponse($nodeId, $response)
    {
        $this->nodeResponseData[$nodeId] = $response;
    }

    public function getAllNodeResponse()
    {
        return self::$instance->nodeResponseData;
    }

    public function getVariables()
    {
        return self::$instance->nodeVariables;
    }

    public function setVariables($nodeId, $response)
    {
        $this->nodeVariables[$nodeId] = $response;
    }

    public function setNodeIndexPosition($nodeId, $index)
    {
        $this->nodeIndexPosition[$nodeId] = $index;
    }

    public function getNodeIndexPosition($nodeId)
    {
        return $this->nodeIndexPosition[$nodeId] ?? null;
    }

    public static function destroy()
    {
        if (self::$instance !== null) {
            self::$instance->nodeIndexPosition = [];
        }

        self::$instance = null;
    }

    private function fetchNodeVariables($flowId)
    {
        if ($flowId === null) {
            return;
        }

        $nodes = FlowNode::select(['flow_id', 'node_id', 'variables'])->where('flow_id', $flowId)->get();

        foreach ($nodes as $node) {
            if ($node->variables !== null) {
                $this->nodeVariables[$node->node_id] = $node->variables;
            }
        }
    }

    private function fetchNodeResponse($flowHistoryId)
    {
        if ($flowHistoryId === null) {
            return;
        }

        $logs = FlowLog::select(['flow_history_id', 'node_id', 'output'])->where('flow_history_id', $flowHistoryId)->get();

        foreach ($logs as $log) {
            if ($log->output !== null) {
                $this->nodeResponseData[$log->node_id] = $log->output;
            }
        }
    }
}
