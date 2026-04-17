<?php

namespace BitApps\Pi\src\Tools\Iterator;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Helpers\MixInputHandler;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\Model\FlowLog;
use BitApps\Pi\src\DTO\FlowToolResponseDTO;
use BitApps\Pi\src\Flow\GlobalNodeVariables;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Tools\FlowToolsFactory;

class IteratorTool
{
    private const MACHINE_SLUG = 'iterator';

    protected $nodeInfoProvider;

    private $flowHistoryId;

    public function __construct(NodeInfoProvider $nodeInfoProvider, $flowHistoryId)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
        $this->flowHistoryId = $flowHistoryId;
    }

    public function execute()
    {
        $flowId = $this->nodeInfoProvider->getFlowId();

        $nodeId = $this->nodeInfoProvider->getNodeId();

        $nodeVariableInstance = GlobalNodeVariables::getInstance($this->flowHistoryId, $flowId);

        $iteratorConfig = $this->nodeInfoProvider->getData()['iterator'] ?? [];

        $extractedData = MixInputHandler::replaceMixTagValue($iteratorConfig['value'] ?? '', 'array-first-element');

        $totalItemLength = Utility::isMultiDimensionArray($extractedData)
        || Utility::isSequentialArray($extractedData)

        ? \count($extractedData) : 0;

        $iteratorStartPosition = 1;

        $iteratorEndPosition = $totalItemLength;

        if (isset($iteratorConfig['start']) && $iteratorConfig['start'] > 1) {
            $iteratorStartPosition = $iteratorConfig['start'];
        }

        if (isset($iteratorConfig['end']) && $iteratorConfig['end'] > 0) {
            $iteratorEndPosition = $iteratorConfig['end'];
        }

        if ($iteratorEndPosition > $totalItemLength) {
            $iteratorEndPosition = $totalItemLength;
        }

        $iteratorResponseData = $this->formatExtractedData($totalItemLength, $extractedData);

        $createdUniqueArray = $this->extractUniqueKeysFromArrays($iteratorResponseData);

        $nodeVariableInstance->setVariables($nodeId, $createdUniqueArray);

        $nodeVariableInstance->setNodeResponse($nodeId, $iteratorResponseData);

        $inputData = [
            'start' => $iteratorStartPosition,
            'end'   => $iteratorEndPosition === 0 ? 1 : $iteratorEndPosition,
        ];

        $details = [
            'app_slug'     => FlowToolsFactory::APP_SLUG,
            'machine_slug' => self::MACHINE_SLUG,
        ];

        return FlowToolResponseDTO::create(
            FlowLog::STATUS['SUCCESS'],
            $inputData,
            $iteratorResponseData,
            'Iterator executed successfully',
            $details,
        );
    }

    /**
     * Extracts all unique keys from an array of arrays and creates a single array
     * containing the first occurrence of each key-value pair.
     *
     * This is useful for creating a template or schema from multiple data items
     * where you want to capture all possible fields that might exist across
     * different array elements.
     *
     * @param array $arrays Array of arrays to extract unique keys from
     *
     * @return array Single array containing all unique keys with their first encountered values
     *
     * Example:
     * Input: [
     *   ['name' => 'John', 'age' => 25],
     *   ['name' => 'Jane', 'email' => 'jane@example.com'],
     *   ['age' => 30, 'city' => 'NYC']
     * ]
     * Output: ['name' => 'John', 'age' => 25, 'email' => 'jane@example.com', 'city' => 'NYC']
     */
    private function extractUniqueKeysFromArrays(array $arrays): array
    {
        $result = [];

        foreach ($arrays as $array) {
            $result += array_diff_key($array, $result);
        }

        unset($result['total_number_of_items'], $result['item_order_position']);

        $result['total_number_of_items'] = \count($arrays);

        $result['item_order_position'] = 0;

        return $result;
    }

    private function formatExtractedData($totalItemLength, $extractedData)
    {
        if ($totalItemLength > 0) {
            $extractedData = array_map(
                function ($item, $index) use ($totalItemLength) {
                    $item = (array) $item;
                    $item['total_number_of_items'] = $totalItemLength;
                    $item['item_order_position'] = $index;

                    return $item;
                },
                $extractedData,
                array_keys($extractedData)
            );
        } else {
            $item = [
                'total_number_of_items' => 1,
                'item_order_position'   => 0
            ];

            if (!\is_array($extractedData) && !\is_object($extractedData)) {
                $item['value'] = $extractedData;
            }

            $extractedData = [$item];
        }

        return $extractedData;
    }
}
