<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\HTTP\Requests\NodeStoreRequest;
use BitApps\Pi\Model\Connection;
use BitApps\Pi\Model\FlowNode;
use BitApps\Pi\Services\FlowService;

final class NodeController
{
    public function store(NodeStoreRequest $request)
    {
        $validated = $request->validated();

        $node = FlowNode::insert($validated);

        if (!$node) {
            return Response::error('Node not created');
        }

        return Response::success($node);
    }

    public function show(Request $request)
    {
        $validated = $request->validate(
            [
                'flow_id' => ['required', 'integer'],
                'node_id' => ['required', 'string', 'sanitize:text'],
            ]
        );

        $node = FlowNode::select(['id', 'machine_slug', 'app_slug', 'data'])->findOne(['flow_id' => $validated['flow_id'], 'node_id' => $validated['node_id']]);

        if (!$node) {
            return Response::error('Node not found');
        }

        $connections = Connection::where('status', Connection::STATUS['verified'])
            ->where('app_slug', $node->app_slug)
            ->get(['id', 'app_slug', 'auth_type', 'connection_name', 'auth_details']);

        return Response::success(['node' => $node, 'connections' => $connections ? $connections : []]);
    }

    public function update(NodeStoreRequest $request)
    {
        $validated = $request->validated();

        $node = FlowNode::findOne(['node_id' => $validated['node_id']]);

        $node->update($validated);

        if (!$node->save()) {
            return Response::error('Node not updated');
        }

        return Response::success($node);
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate(
            [
                'node' => ['required', 'string', 'sanitize:text'],
            ]
        );

        $node = FlowNode::findOne(['node_id' => $validated['node']]);

        if (!$node) {
            return Response::success('Node not found');
        }

        (new FlowNode($node->id))->delete();

        // remove flow_id from webhook table if first node
        if (str_ends_with($validated['node'], '-1')) {
            (new WebhookController())->removeWebhookByFlowId($node->flow_id);
        }

        $flowId = explode('-', $validated['node']);

        $flowIdIndexPosition = 0;

        $cratedNodeId = $flowId[$flowIdIndexPosition] . '-1';

        if ($validated['node'] === $cratedNodeId) {
            FlowService::updateTriggerNodeInCache();
        }

        return Response::success($node->node_id);
    }

    public function createOrUpdate(NodeStoreRequest $request)
    {
        $validated = $request->validated();

        $flowId = $validated['flow_id'];

        $separator = '-';

        $firstNodeId = '1';

        $cratedNodeId = $flowId . $separator . $firstNodeId;

        $nodeId = $validated['node_id'];

        $node = FlowNode::findOne(['flow_id' => $flowId, 'node_id' => $nodeId]);

        if (!$node) {
            $newNode = FlowNode::insert($validated);
            if (!$newNode) {
                return Response::error('Node not created');
            }

            if ($newNode->node_id === $cratedNodeId) {
                FlowService::updateTriggerNodeInCache();
            }

            return Response::success($newNode);
        }

        // machine related variables will removed from db if machine changed
        $validated = $this->resetMachineRelatedData($node->machine_slug, $validated);

        $node->update($validated);

        $node->save();

        if ($node->node_id === $cratedNodeId) {
            FlowService::updateTriggerNodeInCache();
        }

        return Response::success($node);
    }

    public function cloneNode(Request $request)
    {
        $validated = $request->validate(
            [
                'flowId'    => ['required', 'integer'],
                'nodeId'    => ['required', 'string', 'sanitize:text'],
                'newNodeId' => ['required', 'string', 'sanitize:text'],
            ]
        );

        $originalNode = FlowNode::findOne(['flow_id' => $validated['flowId'], 'node_id' => $validated['nodeId']]);

        if (!$originalNode) {
            return Response::error('Original node not found');
        }

        // Check if new node ID already exists
        $existingNode = FlowNode::findOne(['flow_id' => $validated['flowId'], 'node_id' => $validated['newNodeId']]);
        if ($existingNode) {
            return Response::error('Node with this ID already exists');
        }

        $clonedNodeData = $originalNode->getAttributes();
        $clonedNodeData['node_id'] = $validated['newNodeId'];

        $clonedNode = FlowNode::insert($clonedNodeData);

        if (!$clonedNode) {
            return Response::error('Failed to clone node');
        }

        return Response::success($clonedNode);
    }

    private function resetMachineRelatedData($machineSlug, $validated)
    {
        if (
            !isset($validated['machine_slug'])
            || \is_null($validated['machine_slug'])
            || $machineSlug !== $validated['machine_slug']
        ) {
            // remove variables
            $validated['variables'] = null;

            // remove flow_id from webhook table if first node
            if (str_ends_with($validated['node_id'], '-1')) {
                (new WebhookController())->removeWebhookByFlowId($validated['flow_id']);
            }
        }

        return $validated;
    }
}
