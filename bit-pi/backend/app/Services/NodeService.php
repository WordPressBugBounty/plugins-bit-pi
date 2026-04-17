<?php

namespace BitApps\Pi\Services;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Helpers\Parser;
use BitApps\Pi\Model\FlowNode;

class NodeService
{
    public static function saveNodeVariables($flowId, $responses, $nodeId = null)
    {
        $table = Config::get('WP_DB_PREFIX') . Config::VAR_PREFIX . 'flow_nodes';
        $cases = [];
        $ids = [];
        $placeholders = '';
        $variables = '';
        $column = 'variables';

        if (!empty($nodeId)) {
            $responses = [$nodeId => $responses];
        }

        foreach ($responses as $key => $response) {
            $cases[] = 'WHEN node_id = %s THEN %s';
            $ids[] = $key;
            $placeholders .= '%s';

            if (\gettype($response) !== 'array') {
                $response = (array) $response;
            }

            $variables .= $key . '${bf}' . JSON::maybeEncode(Parser::parseResponse($response));

            if (array_key_last($responses) !== $key) {
                $variables .= '${bf}';
                $placeholders .= ',';
            }
        }

        $values = array_merge(explode('${bf}', $variables), $ids);
        $cases = implode(' ', $cases);

        $query = "UPDATE {$table}
          SET {$column} = CASE
            {$cases}
          END
          WHERE node_id IN ({$placeholders}) AND flow_id = %d";

        $values[] = $flowId;

        return FlowNode::raw($query, $values);
    }
}
