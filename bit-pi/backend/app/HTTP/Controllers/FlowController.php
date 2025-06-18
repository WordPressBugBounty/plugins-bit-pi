<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Helpers\Arr;
use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\HTTP\Requests\FlowRequests;
use BitApps\Pi\HTTP\Requests\FlowStoreRequest;
use BitApps\Pi\HTTP\Requests\FlowUpdateRequest;
use BitApps\Pi\Model\Flow;
use BitApps\Pi\Model\FlowLog;
use BitApps\Pi\Model\FlowNode;
use BitApps\Pi\Model\Tag;
use BitApps\Pi\Services\FlowService;
use BitApps\Pi\src\Flow\FlowExecutor;
use WP_Error;

final class FlowController
{
    public function store(FlowStoreRequest $request)
    {
        $validatedData = $request->validated();
        $flowService = new FlowService();
        $status = $flowService->save($validatedData);

        if (!$status) {
            return new WP_Error('bit_pi:invalid:flow_data', $validatedData);
        }

        return $status;
    }

    public function show(FlowRequests $request)
    {
        $validatedData = $request->validated();

        $flow = Flow::select(['id', 'title', 'map', 'data'])
            ->with(
                'nodes',
                function ($query) {
                    $query->select(['id', 'node_id', 'app_slug', 'machine_slug', 'flow_id', "IF(`app_slug` = 'tools', `data`, null) as data"]);
                }
            )
            ->findOne(['id' => $validatedData['flow_id']]);

        if (!$flow) {
            return Response::error('Flow not found');
        }

        $flow['customApps'] = Config::isProActivated() ? (new CustomAppController())->getActiveCustomAppsMeta() : [];

        return Response::success($flow);
    }

    public function search(Request $request)
    {
        $validatedData = $request->validate(
            [
                'searchKeyValue.title' => ['nullable', 'string', 'sanitize:text'],
                'searchKeyValue.tags'  => ['nullable', 'array'],
                'page'                 => ['nullable', 'integer'],
                'limit'                => ['nullable', 'integer'],
            ]
        );

        $tags = $validatedData['searchKeyValue']['tags'];
        $title = strtolower($validatedData['searchKeyValue']['title']);
        $page = $validatedData['page'] ?? 1;
        $limit = $validatedData['limit'] ?? 15;
        $skip = ($page * $limit) - $limit;

        $flowTagTable = Config::withDBPrefix('flow_tag');

        $flowsQuery = Flow::with('nodesCount')->with(
            'nodes',
            function ($query) {
                $query->where('app_slug', '!=', 'tools')
                    ->select(['flow_id', 'app_slug']);
            }
        );

        if (\count($tags) > 0) {
            $tagsPlaceholder = implode(',', array_fill(0, \count($tags), '%d'));

            $flowsQuery
                ->whereRaw(
                    "id IN (
                        SELECT DISTINCT {$flowTagTable}.flow_id 
                        FROM {$flowTagTable} 
                        WHERE {$flowTagTable}.tag_id 
                        IN ({$tagsPlaceholder})
                    )",
                    $tags
                );
        }

        if ($title !== '') {
            $flowsQuery->where('title', 'LIKE', '%' . $title . '%');
        }

        $totalFlows = (int) $flowsQuery->count();

        $flows = $flowsQuery->desc()->skip($skip)->take($limit)
            ->select(['id', 'title', 'run_count', 'is_active'])->get();

        if (!\is_array($flows) || empty($flows)) {
            return Response::success(['flows' => $flows, 'totalFlows' => $totalFlows]);
        }

        $flowIds = Arr::pluck($flows, 'id');
        $tagsTable = Config::withDBPrefix('tags');

        $flowTags = Tag::select(
            [
                $tagsTable . '.id',
                $tagsTable . '.title',
                $tagsTable . '.status',
                $flowTagTable . '.flow_id',
            ]
        )
            ->join(
                'flow_tag',
                $tagsTable . '.id',
                '=',
                $flowTagTable . '.tag_id'
            )
            ->whereIn(
                $flowTagTable . '.flow_id',
                $flowIds
            )
            ->get();

        $groupedTags = [];

        if (\is_array($flowTags)) {
            foreach ($flowTags as $tag) {
                $groupKey = $tag['flow_id'];
                unset($tag['flow_id']);
                $groupedTags[$groupKey][] = $tag;
            }
        }

        array_map(
            function ($flow) use ($groupedTags) {
                $flow->nodesCount = $flow->nodesCount[0]['count'] ?? 0;
                $flow->nodes = \is_array($flow->nodes) ? Arr::pluck($flow->nodes, 'app_slug') : [];
                $flow->flowTags = $groupedTags[$flow['id']] ?? [];
            },
            $flows
        );

        return Response::success(['flows' => $flows, 'totalFlows' => $totalFlows]);
    }

    public function update(FlowUpdateRequest $request)
    {
        $validatedData = $request->validated();
        $tag = $validatedData['tag'] ?? null;
        $flow = $validatedData['flow'];
        $oldTagsId = $tag['oldTagsId'] ?? [];
        $editedFlowField = [];
        $getInsertedLastTags = [];

        if ($flow && \count($flow) > 0) {
            foreach ($flow as $key => $value) {
                if ($key === 'triggerType') {
                    $editedFlowField['trigger_type'] = $value;

                    continue;
                }

                $editedFlowField[$key] = $value;
            }
        }

        $flowService = new FlowService();
        $newTagsId = [];

        if ($tag && $tag['newTags'] && \count($tag['newTags']) > 0) {
            $getInsertedNewTag = $flowService->insertNewTag($tag);

            if (\array_key_exists('validation', $getInsertedNewTag) && $getInsertedNewTag['validation'] === false) {
                return Response::error($getInsertedNewTag['errors']);
            }

            $newTagsId = $getInsertedNewTag['tag_ids'];
            $getInsertedLastTags = $getInsertedNewTag['getLastInsertedTags'];
        }

        $getFlow = Flow::take(1)->find(['id' => $validatedData['id']]);

        $getFlow->update($editedFlowField);

        $getFlow->save();

        $allTags = array_merge($oldTagsId, $newTagsId);

        if ($tag) {
            $flowService->syncTags($validatedData['id'], $allTags);
        }

        $tagsTable = Config::withDBPrefix('tags');
        $flowTagTable = Config::withDBPrefix('flow_tag');

        $flowTags = Tag::select(
            [
                $tagsTable . '.id',
                $tagsTable . '.title',
                $tagsTable . '.status',
                $flowTagTable . '.flow_id',
            ]
        )
            ->join(
                'flow_tag',
                $tagsTable . '.id',
                '=',
                $flowTagTable . '.tag_id'
            )
            ->where(
                $flowTagTable . '.flow_id',
                $validatedData['id']
            )
            ->get();

        $getFlow->flowTags = $flowTags;

        if (isset($editedFlowField['is_active'])) {
            FlowService::updateTriggerNodeInCache();
        }

        if (\count($getInsertedLastTags) > 0) {
            return ['flowDetails' => $getFlow, 'insertedNewTags' => $getInsertedLastTags];
        }

        return Response::success(['flowDetails' => $getFlow]);
    }

    public function destroy(Request $request)
    {
        $getFlow = new Flow($request->id);

        $getFlow->delete();

        FlowService::updateTriggerNodeInCache();

        return Response::success('Flow deleted successfully');
    }

    /**
     * Re-execute flow.
     *
     * @return Response
     */
    public function reExecuteFlow(Request $request)
    {
        $flow = Flow::select(['map', 'listener_type', 'is_hook_capture', 'is_active', 'id'])->with(
            'nodes',
            function ($query) {
                $query->select(['node_id', 'field_mapping', 'app_slug', 'machine_slug', 'variables', 'flow_id']);
            }
        )->findOne(['id' => $request->flow_id]);

        if (!$flow) {
            return Response::error('Flow not found');
        }

        if (empty($flow->nodes)) {
            return Response::error('Flow nodes not found');
        }

        $triggerNode = FlowLog::select('output')->where('flow_history_id', $request->history_id)->where('node_id', $flow->nodes[0]->node_id)->first();

        $triggerData = $triggerNode->output ? JSON::maybeDecode($triggerNode->output) : [];

        FlowExecutor::execute($flow, $triggerData, $request->history_id, 're-execute');

        return Response::success('Flow executed successfully');
    }

    public function variables($flow_id)
    {
        $nodes = FlowNode::where('flow_id', $flow_id)->get(['node_id', 'variables']);

        if (\is_array($nodes)) {
            $nodes = array_filter(
                $nodes,
                function ($node) {
                    return !(\is_null($node->variables) || (\is_array($node->variables) && \count($node->variables) === 0));
                }
            );

            return Response::success([...$nodes]);
        }

        return Response::success([]);
    }
}
