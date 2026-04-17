<?php

namespace BitApps\Pi\Services;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Helpers\Arr;
use BitApps\Pi\Deps\BitApps\WPKit\Helpers\Slug;
use BitApps\Pi\Deps\BitApps\WPValidator\Validator;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\Model\Flow;
use BitApps\Pi\Model\FlowNode;
use BitApps\Pi\Model\FlowTag;
use BitApps\Pi\Model\Tag;

use BitApps\Pi\Rules\UniqueRule;

class FlowService
{
    public function save($flowData)
    {
        $flow = Flow::insert($flowData);

        $templateImportService = new FlowTemplateImportService();

        $importFlow = $templateImportService->processImport($flow->id, $flowData);

        if ($importFlow['isImported']) {
            return $importFlow['flowUpdate'];
        }

        return false;
    }

    public function insertNewTag($tagData)
    {
        $validator = new Validator();

        $validator->make(
            $tagData['newTags'],
            [
                '*' => ['sanitize:text', 'required', new UniqueRule(Tag::class, 'title', 'Tag: :value  can\'t be duplicate')]
            ]
        );

        $errors = $validator->errors();

        if (!empty($errors)) {
            return ['validation' => false, 'errors' => $errors];
        }

        $validated = $validator->validated();
        $newTags = [];

        foreach ($validated as $item) {
            $newTags[] = [
                'title' => $item,
                'slug'  => Slug::generate($item)
            ];
        }

        $getLastInsertedTags = Tag::insert($newTags);
        $lastInsertedTagsId = Arr::pluck($getLastInsertedTags, 'id');

        return ['getLastInsertedTags' => $getLastInsertedTags, 'tag_ids' => $lastInsertedTagsId];
    }

    public static function syncTags($flowId, $tags)
    {
        // getting the saved tag ids from the pivot table for the current flow
        $tagIds = FlowTag::where('flow_id', $flowId)->select(['tag_id'])->get();

        $currentTagIds = Arr::pluck($tagIds, 'tag_id');

        // separating the tags that are in database but removed in the new selection for the current flow
        $detach = array_diff($currentTagIds, $tags);

        if ($detach !== []) {
            // query for detach or delete the tags that are not present in new selected tags
            FlowTag::where('flow_id', $flowId)->whereIn('tag_id', $detach)->delete();
        }

        // separating the tags that are in new selection but not in the database for the current flow
        $attach = array_diff($tags, $currentTagIds);

        if ($attach !== []) {
            $flowTagIds = array_map(fn ($value) => ['flow_id' => $flowId, 'tag_id' => $value], $attach);

            // query for attach or insert the new tags that are not present in the database but in the new selected tags
            FlowTag::insert($flowTagIds);
        }
    }

    public static function exists($appSlug = null, $machineSlug = null, $flowColumns = ['*'], $nodeColumns = ['*'], $operator = '=')
    {
        $flowNodeModel = new FlowNode();

        if ($appSlug) {
            $flowNodeModel->where('app_slug', $operator, $appSlug);
        }

        if ($machineSlug) {
            $flowNodeModel->where('machine_slug', $machineSlug);
        }

        $flowIds = $flowNodeModel->get(['flow_id']);

        $flowIdsArr = \is_array($flowIds) ? Arr::pluck($flowIds, 'flow_id') : [];

        if (\count($flowIdsArr) === 0) {
            return false;
        }

        return Flow::select($flowColumns)
            ->whereIn('id', $flowIdsArr)
            ->with(
                'nodes',
                function ($query) use ($nodeColumns) {
                    $query->select($nodeColumns);
                }
            )->get();
    }

    public static function captureStatusUpdate($flowId, $status)
    {
        $flow = Flow::findOne(['id' => $flowId]);
        $flow->listener_type = Flow::LISTENER_TYPE['NONE'];
        $flow->is_hook_capture = $status;

        return $flow->save() ? $flow : false;
    }

    public static function saveExecutedNodeIds($flowId, $executedNodeIds)
    {
        $flow = Flow::findOne(['id' => $flowId]);

        $flow->executed_node_ids = $executedNodeIds;

        return $flow->save() ? $flow : false;
    }

    public static function updateTriggerNodeInCache()
    {
        $flows = Flow::select('id')->where('is_active', 1)->get();

        $flowIds = Arr::pluck($flows, 'id');

        $nodeIds = array_map(fn ($id) => "{$id}-1", $flowIds);

        if (empty($nodeIds)) {
            return Utility::deleteTransientCache('trigger_nodes');
        }

        $nodes = FlowNode::select(['app_slug', 'machine_slug', 'field_mapping', 'data', 'node_id'])
            ->whereIn('node_id', $nodeIds)
            ->get();

        Utility::clearFlowSchedules();

        $data = [];

        $scheduleNodes = [];

        foreach ($nodes as $node) {
            $machineSlug = $node->machine_slug;
            if (\in_array($node->app_slug, ['wordPress', 'wordPressActionHooks']) && $node->machine_slug === 'addAction') {
                $machineSlug = Utility::convertToMachineSlug($node->field_mapping->configs->{'hook-name'}->value) ?? null;
            }

            $data[$node->app_slug][] = $machineSlug;

            $jsonDecodedData = $node->data;

            if ($jsonDecodedData && isset($jsonDecodedData->schedule)) {
                $scheduleNodes[$node->node_id] = wp_json_encode($jsonDecodedData->schedule);
            }
        }

        if (empty($scheduleNodes)) {
            Utility::deleteTransientCache('schedules');
        } else {
            Utility::setTransientCache('schedules', $scheduleNodes, DAY_IN_SECONDS);
        }

        Utility::setTransientCache('trigger_nodes', $data, DAY_IN_SECONDS);

        return $data;
    }

    public static function getOrUpdateTriggerNodesFromCache()
    {
        $triggerNodes = Utility::getTransientCache('trigger_nodes');

        if (empty($triggerNodes)) {
            return self::updateTriggerNodeInCache();
        }

        return $triggerNodes;
    }
}
