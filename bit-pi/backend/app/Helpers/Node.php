<?php

namespace BitApps\Pi\Helpers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


class Node
{
    /**
     * Get node info by node id.
     *
     * @param int   $nodeId
     * @param array $nodes
     *
     * @return collection|void
     */
    public static function getNodeInfoById($nodeId, $nodes)
    {
        if (empty($nodes)) {
            return;
        }

        foreach ($nodes as $node) {
            if ($node['node_id'] === $nodeId) {
                return $node;
            }
        }
    }

    /**
     * Get condition node info by node id.
     *
     * @param int        $nodeId
     * @param collection $nodeInfo
     *
     * @return collection|false|void
     */
    public static function getConditionsByNodeId($nodeId, $nodeInfo)
    {
        if (empty($nodeInfo->data->conditions)) {
            return false;
        }

        foreach ($nodeInfo->data->conditions as $condition) {
            if ($condition->id === $nodeId && isset($condition->condition)) {
                return [
                    'condition' => $condition->condition,
                    'title'     => $condition->title ?? '',
                ];
            }
        }
    }

    public static function searchNodeKey($fieldMap, $keyName, $value)
    {
        return self::searchRecursive((array) $fieldMap, $keyName, $value);
    }

    private static function searchRecursive($fieldMap, $keyName, $value)
    {
        foreach ($fieldMap as $key => $val) {
            if (\is_object($val)) {
                $val = (array) $val;
            }

            if ($key === $keyName && $val === $value) {
                return $fieldMap;
            }

            if (\is_array($val)) {
                $result = self::searchRecursive($val, $keyName, $value);

                if ($result !== null) {
                    return $result;
                }
            }
        }
    }
}
