<?php

namespace BitApps\Pi\Services;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Helpers\Arr;
use BitApps\Pi\Model\CustomApp;
use BitApps\Pi\Model\Flow;
use BitApps\Pi\Model\FlowNode;

class CustomAppService
{
    /**
     * Create a custom app and assign its generated slug.
     *
     * The slug rule (APP_SLUG_PREFIX plus a dash-less uuid) is what the node
     * executor resolves apps by, so the admin controller and the MCP tool
     * must generate it identically — hence one implementation.
     *
     * @return array{app?: CustomApp, slug?: string, error?: 'insert'|'slug'}
     */
    public static function create(array $attributes): array
    {
        $app = CustomApp::insert($attributes);

        if (!$app) {
            return ['error' => 'insert'];
        }

        $slug = CustomApp::APP_SLUG_PREFIX . str_replace('-', '', wp_generate_uuid4());

        if (!CustomApp::findOne(['id' => $app->id])->update(['slug' => $slug])) {
            return ['error' => 'slug'];
        }

        return ['app' => $app, 'slug' => $slug];
    }

    public static function findFlowTitlesBySlug($columnName, $columnValue)
    {
        $flowIds = FlowNode::select(['flow_id'])->where($columnName, $columnValue)->get();

        if (\count($flowIds) > 0) {
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
