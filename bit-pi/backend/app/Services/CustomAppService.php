<?php

namespace BitApps\Pi\Services;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Helpers\Arr;
use BitApps\Pi\Model\Flow;
use BitApps\Pi\Model\FlowNode;

class CustomAppService
{
    public static function findFlowTitlesBySlug($columnName, $columnValue)
    {
        $flowIds = FlowNode::select(['flow_id'])->where($columnName, $columnValue)->get();

        if ($flowIds) {
            $flows = Flow::select(['id', 'title'])->whereIn('id', Arr::pluck($flowIds, 'flow_id'))->get();

            $flowTitles = Arr::pluck($flows, 'title');

            if (\count($flowTitles) > 2) {
                $flowTitles = \array_slice($flowTitles, 0, 2);
                $flowTitles[] = '...';
            }

            return $flowTitles;
        }

        return false;
    }
}
