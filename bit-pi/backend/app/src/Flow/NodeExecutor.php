<?php

namespace BitApps\Pi\src\Flow;

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Helpers\Node;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\Model\CustomApp;
use BitApps\Pi\Model\FlowLog;
use BitApps\Pi\src\Exception\MissingKeyException;
use BitApps\Pi\src\Log\LogHandler;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}


class NodeExecutor
{
    public const BASE_INTEGRATION_NAMESPACE = 'BitApps\Pi\src\Integrations\\';

    public const BASE_INTEGRATION_NAMESPACE_PRO = 'BitApps\PiPro\src\Integrations\\';

    /**
     * Execute the node action.
     *
     * @param mixed $currentNodeInfo
     * @param mixed $flowHistoryId
     * @param mixed $flowId
     *
     * @return array
     */
    public function handleActionNode($currentNodeInfo, $flowHistoryId, $flowId)
    {
        $nodeVariableInstance = GlobalNodeVariables::getInstance($flowHistoryId, $flowId);

        $app = $this->isExistClass($currentNodeInfo->app_slug);

        if (!$app) {
            throw new Exception(esc_html("Error: Platform '{$currentNodeInfo->app_slug}' not found."));
        }

        $startTime = microtime(true);

        $action = new $app(new NodeInfoProvider($currentNodeInfo));

        $response = $action->execute();

        $endTime = microtime(true);

        $duration = number_format($endTime - $startTime, 2);

        if (!isset($response['input'], $response['output'], $response['status'])) {
            throw new MissingKeyException();
        }

        if (\gettype($response['output']) !== 'array') {
            $response['output'] = (array) $response['output'];
        }

        if (
            !empty($response['output'])
            && (Utility::isSequentialArray($response['output'])
            || Utility::isMultiDimensionArray($response['output']))
        ) {
            $response['output'] = ['data' => $response['output']];
        }

        $nodeVariableInstance->setVariables($currentNodeInfo->node_id, $response['output']);

        $nodeVariableInstance->setNodeResponse($currentNodeInfo->node_id, $response['output']);

        $details = [
            'duration'     => $duration,
            'data_size'    => number_format(mb_strlen($log['input'] ?? '', '8bit') / 1024, 2),
            'app_slug'     => $currentNodeInfo->app_slug ?? '',
            'machine_slug' => $currentNodeInfo->machine_slug ?? '',
        ];

        LogHandler::getInstance()->addLog(
            $flowHistoryId,
            $currentNodeInfo->node_id,
            $response['status'],
            $response['input'],
            $response['output'],
            $response['messages'] ?? null,
            $details
        );

        return $response['status'];
    }

    public function handleTriggerNode($currentNodeInfo, $triggerData, $nodeInstance, $flowHistoryId)
    {
        $nodeInstance->setVariables($currentNodeInfo->node_id, $triggerData);

        $nodeInstance->setNodeResponse($currentNodeInfo->node_id, $triggerData);

        LogHandler::getInstance()->addLog(
            $flowHistoryId,
            $currentNodeInfo->node_id,
            FlowLog::STATUS['SUCCESS'],
            [],
            $triggerData,
            null,
            [
                'app_slug'     => $currentNodeInfo->app_slug ?? '',
                'machine_slug' => $currentNodeInfo->machine_slug ?? '',
            ]
        );

        return false;
    }

    /**
     * Check if action exist.
     *
     * @param string $appSlug
     * @param mixed $appType
     *
     * @return string || bool
     */
    public function isExistClass($appSlug, $appType = 'Action')
    {
        if (strpos($appSlug, CustomApp::APP_SLUG_PREFIX) !== false) {
            $appSlug = ucfirst(CustomApp::APP_SLUG);
        } else {
            $appSlug = ucfirst($appSlug);
        }

        if (class_exists(self::BASE_INTEGRATION_NAMESPACE . "{$appSlug}\\{$appSlug}{$appType}")) {
            return self::BASE_INTEGRATION_NAMESPACE . "{$appSlug}\\{$appSlug}{$appType}";
        }

        if (class_exists(self::BASE_INTEGRATION_NAMESPACE_PRO . "{$appSlug}\\{$appSlug}{$appType}")) {
            return self::BASE_INTEGRATION_NAMESPACE_PRO . "{$appSlug}\\{$appSlug}{$appType}";
        }

        return false;
    }

    /**
     * Check error node id is exists in next node variables.
     *
     * @param int    $flowId
     * @param object $errorNode
     *
     * @return bool
     */
    public function hasErrorNodeIdInNextNodes($flowId, $errorNode)
    {
        $queue[] = $errorNode;

        $foundMatchingNodeId = false;

        $nodeInstance = GlobalNodes::getInstance($flowId);

        $nodes = $nodeInstance->getAllNodeData();

        while ($queue !== []) {
            $currentNode = array_shift($queue);
            $id = $currentNode->id;

            if ($foundMatchingNodeId) {
                $nodeDetails = Node::getNodeInfoById($id, $nodes);
                $result = Node::searchNodeKey(JSON::maybeDecode($nodeDetails['field_mapping']), 'nodeId', $errorNode->id);

                $hasErrorId = $result !== null;

                if ($hasErrorId) {
                    return true;
                }
            }

            if ($id === $errorNode->id && isset($currentNode->next)) {
                $foundMatchingNodeId = true;
                $queue[] = $currentNode->next;
            }

            if (isset($currentNode->next)) {
                if ($currentNode->type === 'router') {
                    foreach ($currentNode->next as $childNode) {
                        $queue[] = $childNode;
                    }
                } else {
                    $queue[] = $currentNode->next;
                }
            } else {
                $queue = [];
            }
        }

        return false;
    }
}
