<?php

namespace BitApps\Pi\src\Flow;

use BitApps\Pi\Model\FlowNode;

if (!defined('ABSPATH')) {
    exit;
}


class GlobalNodes
{
    private static $instance;

    private $nodeData;

    private function __construct($flowId)
    {
        $this->nodeData = $this->fetchNodeData($flowId);
    }

    public static function getInstance($flowId)
    {
        if (self::$instance === null) {
            self::$instance = new self($flowId);
        }

        return self::$instance;
    }

    public function getAllNodeData()
    {
        return $this->nodeData;
    }

    public static function destroy()
    {
        self::$instance = null;
    }

    private function fetchNodeData($flowId)
    {
        return FlowNode::where('flow_id', $flowId)->all();
    }
}
