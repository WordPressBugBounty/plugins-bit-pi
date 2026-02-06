<?php

namespace BitApps\Pi\src\Tools;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Log\LogHandler;
use BitApps\Pi\src\Tools\AiAgent\AiAgent;
use BitApps\Pi\src\Tools\Condition\ConditionTool;
use BitApps\Pi\src\Tools\Condition\DefaultConditionTool;
use BitApps\Pi\src\Tools\Csv\CsvTool;
use BitApps\Pi\src\Tools\Delay\DelayTool;
use BitApps\Pi\src\Tools\ImageHelper\ImageHelperTool;
use BitApps\Pi\src\Tools\Iterator\IteratorTool;
use BitApps\Pi\src\Tools\JsonParser\JsonParserTool;
use BitApps\Pi\src\Tools\Repeater\RepeaterTool;
use BitApps\Pi\src\Tools\Xml\XmlTool;

/**
 * Factory class for creating and executing flow tools in the automation workflow.
 *
 * This class is responsible for instantiating the appropriate tool class based on
 * the node type and handling the execution flow with proper logging.
 */
class FlowToolsFactory
{
    /**
     * Application slug identifier.
     *
     * @var string
     */
    public const APP_SLUG = 'tools';

    /**
     * Node type identifier for iterator tool.
     *
     * @var string
     */
    private const ITERATOR_TOOL = 'iterator';

    /**
     * Node type identifier for repeater tool.
     *
     * @var string
     */
    private const REPEATER_TOOL = 'repeater';

    /**
     * Creates and returns the appropriate flow tool instance based on node type.
     *
     * @param object $currentNode     Current node object containing node properties
     * @param array  $currentNodeInfo Information about the current node
     * @param int    $flowHistoryId   ID of the current flow execution history
     * @param bool $isTestTool      Flag indicating if the tool is being used for testing
     *
     * @return false|object Returns the instantiated tool object or false if not found
     */
    public static function createFlowTool($currentNode, $currentNodeInfo, $flowHistoryId, $isTestTool = false)
    {
        switch ($currentNode->type) {
            case 'condition-logic':
                return new ConditionTool($currentNode);

            case 'default-condition-logic':
                return new DefaultConditionTool($currentNode);

            case 'delay':
                if (!class_exists(DelayTool::class)) {
                    return false;
                }

                return new DelayTool(new NodeInfoProvider($currentNodeInfo), $flowHistoryId, $isTestTool);

            case 'iterator':
                if (!class_exists(IteratorTool::class)) {
                    return false;
                }

                return new IteratorTool(new NodeInfoProvider($currentNodeInfo), $flowHistoryId);

            case 'repeater':
                if (!class_exists(RepeaterTool::class)) {
                    return false;
                }

                return new RepeaterTool(new NodeInfoProvider($currentNodeInfo), $flowHistoryId);

            case 'jsonParser':
                if (!class_exists(JsonParserTool::class)) {
                    return false;
                }

                return new JsonParserTool(new NodeInfoProvider($currentNodeInfo), $flowHistoryId);

            case 'xml':
                if (!class_exists(XmlTool::class)) {
                    return false;
                }

                return new XmlTool(new NodeInfoProvider($currentNodeInfo), $flowHistoryId);

            case 'csv':
                if (!class_exists(CsvTool::class)) {
                    return false;
                }

                return new CsvTool(new NodeInfoProvider($currentNodeInfo), $flowHistoryId);

            case 'imageHelper':
                if (!class_exists(ImageHelperTool::class)) {
                    return false;
                }

                return new ImageHelperTool(new NodeInfoProvider($currentNodeInfo), $flowHistoryId);

            case 'aiAgent':
                if (!class_exists(AiAgent::class)) {
                    return false;
                }

                return new AiAgent(new NodeInfoProvider($currentNodeInfo), $flowHistoryId);

            default:
                return false;
        }
    }

    /**
     * Executes the appropriate tool and logs the execution results.
     *
     * This method creates the tool instance, executes it, logs the results,
     * and returns the appropriate response based on the tool type.
     *
     * @param object $currentNode     Current node object containing node properties
     * @param array  $currentNodeInfo Node information array
     * @param int    $flowHistoryId   ID of the current flow execution history
     *
     * @return mixed Returns either:
     *               - Input data for iterator tool
     *               - Loop configuration array for repeater tool
     *               - Boolean indicating if next node should execute for other tools
     */
    public static function executeToolWithLogging($currentNode, $currentNodeInfo, $flowHistoryId)
    {
        $flowTool = self::createFlowTool($currentNode, $currentNodeInfo, $flowHistoryId);

        $nodeType = $currentNode->type;

        if (!$flowTool) {
            return false;
        }

        $response = $flowTool->execute();


        $nodeId = $response['nodeId'] ?? $currentNode->id;

        if ($response['shouldSaveToLog']) {
            LogHandler::getInstance()->addLog(
                $flowHistoryId,
                $nodeId,
                $response['status'],
                $response['input'],
                $response['output'],
                $response['message'] ?? null,
                $response['details'] ?? []
            );
        }

        if ($nodeType === self::ITERATOR_TOOL) {
            return $response['input'];
        }

        if ($nodeType === self::REPEATER_TOOL) {
            $loopInitializeValue = 1;

            return [
                'start' => $loopInitializeValue,
                'end'   => $response['input']['repeat'] ?? 1,
            ];
        }

        return $response['isNextNodeBlocked'] ?? false;
    }
}
