<?php

namespace BitApps\Pi\src\Flow;

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Helpers\MixInputHandler;
use BitApps\Pi\Helpers\Utility;
use Exception;

if (!\defined('ABSPATH')) {
    exit;
}


class NodeInfoProvider
{
    private $flowId;

    private $nodeId;

    private $previousNodeId;

    private $appSlug;

    private $machineSlug;

    private $fieldMap;

    private $variables;

    private $data;

    public function __construct($node)
    {
        $this->flowId = $node['flow_id'];
        $this->nodeId = $node['node_id'];
        $this->appSlug = $node['app_slug'];
        $this->machineSlug = $node['machine_slug'];
        $this->fieldMap = JSON::decode(JSON::encode($node['field_mapping']), true);
        $this->data = JSON::decode(JSON::encode($node['data']), true);
    }

    /**
     * Retrieves and app configuration data from the field map.
     *
     * This method fetches data from the `fieldMap['configs']` array using the specified path.
     * If no path is provided, the entire `configs` array is processed. The retrieved data is
     * passed to `MixInputHandler::processConfigs` for recursive processing of values.
     *
     * @param null|string $path The path within the `configs` array to retrieve. If null,
     *                          processes the entire `configs` array.
     *
     * @return array|string The processed configuration data. If the data is a non-iterable type
     *                      (e.g., a string), it is returned as-is.
     */
    public function getFieldMapConfigs($path = null)
    {
        $valueFromPath = Utility::getValueFromPath($this->fieldMap['configs'] ?? [], $path);

        return MixInputHandler::processConfigs($valueFromPath);
    }

    /**
     * Processes and retrieves structured field map data.
     *
     * This method extracts the `data` key from the `fieldMap` property, processes it
     * using the `MixInputHandler::processData` method, and returns the resulting
     * structured payload. The `data` input is expected to contain a collection of
     * field pairs, where each pair includes a `path` (dot-notated string) and a `value`.
     *
     * @return array the structured and processed field map data
     */
    public function getFieldMapData()
    {
        return MixInputHandler::processData($this->fieldMap['data'] ?? []);
    }

    /**
     * Retrieves and processes repeater field mappings based on the provided path and options.
     *
     * This method fetches a specific repeater field mapping from the `fieldMap['repeaters']` array
     * by following the provided `$path`. The retrieved data is then processed using
     * `MixInputHandler::processRepeaters` to format it according to the specified options.
     *
     * @param null|string $path               The path to locate the specific repeater field mapping
     *                                        within the `fieldMap['repeaters']` array. If null, the
     *                                        entire repeaters array is processed.
     * @param bool        $isArrayAssociative determines whether the resulting array should be
     *                                        associative (`true`) or indexed (`false`)
     * @param bool        $isArrayColumn      if true, the method formats the output as an array of
     *                                        columns using the specified key and value columns
     * @param string      $keyColumnName      the column name to use as keys in the resulting array
     *                                        (applicable when `$isArrayColumn` is true)
     * @param string      $valueColumnName    the column name to use as values in the resulting array
     *                                        (applicable when `$isArrayColumn` is true)
     *
     * @throws Exception if an error occurs while processing the repeaters
     *
     * @return array The processed repeater field mapping. The format depends
     *               on the provided options, such as associative or columnar format.
     */
    public function getFieldMapRepeaters(
        $path = null,
        $isArrayAssociative = false,
        $isArrayColumn = true,
        $keyColumnName = 'key',
        $valueColumnName = 'value'
    ) {
        $valueFromPath = Utility::getValueFromPath($this->fieldMap['repeaters'] ?? [], $path);

        return MixInputHandler::processRepeaters($valueFromPath, $isArrayAssociative, $isArrayColumn, $keyColumnName, $valueColumnName);
    }

    public function getFlowId()
    {
        return $this->flowId;
    }

    public function getNodeId()
    {
        return $this->nodeId;
    }

    public function getPreviousNodeId()
    {
        return $this->previousNodeId;
    }

    public function getAppSlug()
    {
        return $this->appSlug;
    }

    public function getMachineSlug()
    {
        return $this->machineSlug;
    }

    public function getFieldMap($path = null)
    {
        if ($path) {
            return Utility::getValueFromPath($this->fieldMap, $path);
        }

        return $this->fieldMap;
    }

    public function getVariables()
    {
        return $this->variables;
    }

    public function getData()
    {
        return $this->data;
    }
}
