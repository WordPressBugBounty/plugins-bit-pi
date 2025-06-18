<?php

namespace BitApps\Pi\src\Flow;

use BitApps\Pi\Model\Flow;

if (!\defined('ABSPATH')) {
    exit;
}


class GlobalFlow
{
    private static $instance;

    private $flow;

    private function __construct($flowId)
    {
        $this->flow = $this->fetchFlow($flowId);
    }

    public static function getInstance($flowId)
    {
        if (self::$instance === null) {
            self::$instance = new self($flowId);
        }

        return self::$instance;
    }

    public static function getFlowFieldValue($columnName)
    {
        global $globalFlowId;

        $flowInstance = self::getInstance($globalFlowId);

        return $flowInstance->flow->{$columnName} ?? null;
    }

    public static function destroy()
    {
        global $globalFlowId;

        $globalFlowId = null;

        self::$instance = null;
    }

    private function fetchFlow($flowId)
    {
        if ($flowId === null) {
            return [];
        }

        return Flow::select(['id', 'title', 'is_active'])->where('id', $flowId)->first();
    }
}
