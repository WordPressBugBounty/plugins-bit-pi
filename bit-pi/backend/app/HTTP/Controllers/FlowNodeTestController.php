<?php

namespace BitApps\Pi\HTTP\Controllers;

use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\Helpers\Parser;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\Model\Flow;
use BitApps\Pi\Model\FlowNode;
use BitApps\Pi\Services\NodeService;
use BitApps\Pi\src\Flow\GlobalNodeVariables;
use BitApps\Pi\src\Flow\NodeExecutor;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Tools\FlowToolsFactory;

/**
 * Controller for testing and running flow nodes individually.
 *
 * Handles execution of individual nodes in the flow.
 */
class FlowNodeTestController
{
    /**
     * Execute a specific flow node and return its response.
     *
     * This method validates the input, retrieves the node data,
     * sets up the execution environment, and runs the node based on its type.
     *
     * @param Request $request The incoming HTTP request
     *
     * @return Response Success response with execution results or error response
     */
    private const FLOW_ID_POSITION = 0;

    private const FLOW_TOOLS_APP_SLUG = 'tools';

    private const CONDITION_LOGIC_MACHINE_SLUG = 'condition-logic';

    public function runNode(Request $request)
    {
        $validatedData = $request->validate(
            [
                'node_id' => ['required', 'string', 'sanitize:text'],
            ]
        );

        $nodeId = $validatedData['node_id'];

        [$nodeId, $previousNodeId] = $this->splitNodeId($nodeId);

        $nodeData = $this->getNodeData($nodeId);

        if (!$nodeData) {
            return Response::error('Node not found', 404);
        }

        $flowId = $this->getFlowId($nodeId);

        $nodeType = isset($nodeData->data->schedule) ? 'Trigger' : 'Action';

        $this->loadNodeVariables($flowId);

        $executionResponse = $this->executeNode($nodeData, $flowId, $nodeType, $previousNodeId);

        $nodeResponse = $this->prepareNodeResponse($executionResponse);

        if ($nodeData['machine_slug'] === 'condition') {
            return Response::success($nodeResponse);
        }

        $this->saveNodeVariables($nodeData, $nodeResponse, $flowId, $executionResponse, $nodeType);

        return $executionResponse['status'] === 'error'
            ? Response::error($nodeResponse)
            : Response::success($nodeResponse);
    }

    private function splitNodeId(string $nodeId): array
    {
        $previousNodeId = null;

        if (substr_count($nodeId, '-') >= 2) {
            $previousNodeId = substr(strrchr($nodeId, '-'), 1);
            $nodeId = substr($nodeId, 0, strrpos($nodeId, '-'));
        }

        return [$nodeId, $previousNodeId];
    }

    private function getNodeData(string $nodeId)
    {
        return FlowNode::where('node_id', $nodeId)->first();
    }

    private function getFlowId(string $nodeId): string
    {
        return explode('-', $nodeId)[self::FLOW_ID_POSITION];
    }

    private function loadNodeVariables($flowId)
    {
        $instance = GlobalNodeVariables::getInstance(null, $flowId);
        $variables = $instance->getVariables();

        foreach ($variables as $key => $variable) {
            $instance->setNodeResponse($key, Parser::parseArrayStructure($variable));
        }

        return $variables;
    }

    private function executeNode($nodeData, $flowId, $nodeType, $previousNodeId = null)
    {
        $appSlug = $nodeData['app_slug'];

        if ($appSlug === self::FLOW_TOOLS_APP_SLUG) {
            return $this->executeFlowTool($nodeData, $flowId, $previousNodeId);
        }

        return $this->executeAppNode($nodeData, $nodeType);
    }

    private function executeFlowTool($nodeData, $flowId, $previousNodeId)
    {
        $nodeInfo = (object) [
            'type' => $nodeData['machine_slug'],
            'id'   => $nodeData['node_id'],
        ];

        if ($previousNodeId) {
            $nodeInfo->id = $nodeData['node_id'] . '-' . $previousNodeId;
            $nodeInfo->previous = $nodeData['node_id'];
            $nodeInfo->type = self::CONDITION_LOGIC_MACHINE_SLUG;
        }

        return FlowToolsFactory::createFlowTool($nodeInfo, $nodeData, $flowId, true)->execute();
    }

    private function executeAppNode($nodeData, $nodeType)
    {
        $appClass = (new NodeExecutor())->isExistClass($nodeData->app_slug, $nodeType);
        $provider = new NodeInfoProvider($nodeData);
        $instance = new $appClass($provider);

        $executionResponse = $nodeType === 'Trigger' ? $instance->pull() : $instance->execute();

        if (
            !empty($executionResponse['output'])
            && (Utility::isSequentialArray($executionResponse['output'])
                || Utility::isMultiDimensionArray($executionResponse['output']))
        ) {
            $executionResponse['output'] = ['data' => $executionResponse['output']];
        }

        return $executionResponse;
    }

    private function prepareNodeResponse($executionResponse): array
    {
        return [
            'input'  => $executionResponse['input'] ?? [],
            'output' => $executionResponse['output'] ?? [],
        ];
    }

    private function saveNodeVariables($nodeData, $nodeResponse, $flowId, $executionResponse, $nodeType)
    {
        $instance = GlobalNodeVariables::getInstance(null, $flowId);
        $nodeId = $nodeData['node_id'];

        $currentNodeVariable = $nodeResponse['output'];

        if (\in_array($nodeData['machine_slug'], ['repeater', 'iterator'])) {
            $currentNodeVariable = $instance->getVariables()[$nodeId];
        }

        if ($nodeType === 'Trigger') {
            $firstIndexPosition = 0;
            $currentNodeVariable = \is_array($executionResponse)
                ? ($executionResponse['output'][$firstIndexPosition] ?? [])
                : [];
        }

        NodeService::saveNodeVariables($flowId, $currentNodeVariable, $nodeId);
    }
}
