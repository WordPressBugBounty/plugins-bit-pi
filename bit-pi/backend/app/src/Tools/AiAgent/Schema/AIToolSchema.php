<?php

namespace BitApps\Pi\src\Tools\AiAgent\Schema;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Helpers\MixInputHandler;
use BitApps\Pi\Helpers\Node;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\Model\FlowNode;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Mcp\McpClient;

/**
 * Universal AI tool schema generator.
 *
 * This class converts application tools into AI-compatible function schemas.
 * Works with all AI providers (OpenAI, Anthropic, Groq, etc.) using the
 * standard function calling format.
 */
class AIToolSchema
{
    public const MCP_TOOL_SCHEMA_CLEANUP_HOOK = 'mcp_tool_schema_cleanup';

    /**
     * Generate tool schemas for multiple tool node IDs.
     *
     * @param mixed $agentSubnode
     * @param mixed $nodes
     *
     * @return array Array of tool schemas
     */
    public static function generateSchemas($agentSubnode, $nodes): array
    {
        $schemas = [];
        $toolNodeIds = $agentSubnode['tools'] ?? [];

        foreach ($toolNodeIds as $toolNodeId) {
            $node = Node::getNodeInfoById($toolNodeId, $nodes);
            $mcpToolSchema = [];
            $schema = [];
            if (empty($node)) {
                continue;
            }

            if ($node->app_slug === 'mcpClientTool') {
                $cached = self::decompressSchemas($node->data->schemas ?? null);

                if ($cached !== null) {
                    $mcpToolSchema = self::normalizeToolSchemasAfterCacheDecode($cached);
                } else {
                    $nodeInfo = new NodeInfoProvider($node);
                    $configs = $nodeInfo->getFieldMapConfigs();
                    $connectionId = $configs['connection-id']['value'] ?? null;
                    $transport = $configs['server-transport']['value'] ?? 'http';
                    $serverUrl = MixInputHandler::replaceMixTagValue($configs['server-url']['value'] ?? []);
                    $selectedTools = $configs['selected-tools']['value'] ?? [];
                    $exceptSelectedTools = $configs['except-selected-tools']['value'] ?? [];

                    $mcpClient = new McpClient($serverUrl, $transport, $connectionId);

                    $result = $mcpClient->getTools();
                    if ($result['success']) {
                        $mcpToolSchema = self::convertMcpToolsToSchemas(
                            $result['tools'] ?? [],
                            $selectedTools,
                            $exceptSelectedTools,
                            $toolNodeId
                        );
                        self::storeToolSchemasInNodeData($node, $mcpToolSchema);
                    }
                }
            } else {
                $schema = (new self())->createSchema($node);
            }

            if (!empty($schema)) {
                $schemas[] = $schema;
            }

            if (!empty($mcpToolSchema)) {
                $schemas = array_merge($schemas, $mcpToolSchema);
            }
        }

        return $schemas;
    }

    /**
     * Convert a JSON example to JSON Schema format for LLM structured output.
     *
     * Takes a user-provided JSON example and generates a corresponding JSON Schema
     * that can be used with LLM's structured output feature.
     *
     * Example Input:
     * {
     *     "name": "John Doe",
     *     "hobbies": ["reading", "traveling", "playing"]
     * }
     *
     * Example Output:
     * {
     *     "type": "json_schema",
     *     "json_schema": {
     *         "name": "response_schema",
     *         "strict": true,
     *         "schema": {
     *             "type": "object",
     *             "properties": {
     *                 "name": { "type": "string" },
     *                 "hobbies": {
     *                     "type": "array",
     *                     "items": { "type": "string" }
     *                 }
     *             },
     *             "required": ["name", "hobbies"],
     *             "additionalProperties": false
     *         }
     *     }
     * }
     *
     * @param string $json The JSON example string
     *
     * @return array The JSON Schema structure for LLM
     */
    public static function buildResponseStructureFormat(string $json, string $formatType): array
    {
        if (\in_array($formatType, ['text', 'json_object'])) {
            return [
                'type' => $formatType,
            ];
        }

        $decoded = JSON::maybeDecode($json, true);

        if (!\is_array($decoded)) {
            return [];
        }

        $schema = [];

        if ($formatType === 'json_example') {
            $schema = (new self())->generateSchemaFromValue($decoded);
        } else {
            $schema = $decoded;
        }

        if (empty($schema)) {
            return [];
        }

        return [
            'type'        => 'json_schema',
            'json_schema' => [
                'name'   => 'response_schema',
                'strict' => false,
                'schema' => $schema,
            ],
        ];
    }

    /**
     * Remove stored MCP schemas for nodes using "tools-to-include: all".
     */
    public static function cleanupMcpSchemasForAllToolsSelection(): void
    {
        $nodes = FlowNode::where('app_slug', 'mcpClientTool')->get(['id', 'field_mapping', 'data']);

        if (empty($nodes)) {
            return;
        }

        foreach ($nodes as $node) {
            if (!self::isToolsToIncludeAll($node->field_mapping ?? null)) {
                continue;
            }

            $data = $node->data ? JSON::decode(JSON::encode($node->data), true) : [];

            if (!\is_array($data) || !\array_key_exists('schemas', $data)) {
                continue;
            }

            unset($data['schemas']);

            $node->update(['data' => $data]);
            $node->save();
        }
    }

    /**
     * Create a schema for a single tool node.
     *
     * @param array $node The node data containing tool configuration
     *
     * @return array The formatted schema compatible with all AI providers
     */
    protected function createSchema($node): array
    {
        $toolConfig = $this->getToolConfig($node);
        $toolName = $this->generateToolName($node);
        $description = $this->getDescription($toolConfig);
        $parameters = $this->extractParameters($node);

        if (empty($parameters['properties'])) {
            $parameters = (object) [];
        }

        return [
            'type'     => 'function',
            'function' => [
                'name'        => $toolName,
                'description' => $description,
                'parameters'  => $parameters,
            ],
        ];
    }

    /**
     * Extract and format parameters from node configuration.
     *
     * @param array $node The node data
     *
     * @return array The formatted parameters object
     */
    protected function extractParameters($node)
    {
        $nodeInfo = new NodeInfoProvider($node);

        $fieldMap = $nodeInfo->getFieldMap();
        $fieldMapData = $fieldMap['data'] ?? [];

        $repeaters = $this->getModelDefinedMappings($fieldMap['repeaters'] ?? []);

        $properties = [];
        $required = [];

        if (!empty($fieldMapData)) {
            foreach ($fieldMapData as $fieldKey => $fieldMap) {
                if (!isset($fieldMap['path'])) {
                    continue;
                }
                $paramInfo = $this->createParameterInfo($fieldMap['path']);

                if (!empty($paramInfo) && isset($fieldMap['modelDefined'])) {
                    $properties[$fieldMap['path']] = $paramInfo['property'];

                    if ($paramInfo['required']) {
                        $required[] = $fieldKey;
                    }
                }
            }
        }

        if (!empty($repeaters)) {
            foreach ($repeaters as $repeater) {
                $properties[$repeater] = [
                    'type'        => 'string',
                    'description' => "The {$repeater} parameter",
                ];
            }
        }

        $fieldMapConfigData = $fieldMap['configs'] ?? [];

        if (!empty($fieldMapConfigData)) {
            foreach ($fieldMapConfigData as $fieldKey => $fieldMap) {
                if (isset($fieldMap['modelDefined'])) {
                    $properties[$fieldKey] = [
                        'type'        => 'string',
                        'description' => "The {$fieldKey} parameter",
                    ];
                }
            }
        }

        return [
            'type'       => 'object',
            'properties' => $properties,
            'required'   => $required,
        ];
    }

    /**
     * Get tool configuration from node data.
     *
     * @param array $node The node data
     *
     * @return array The tool configuration
     */
    protected function getToolConfig($node)
    {
        return $node->data->tool ?? [];
    }

    /**
     * Generate tool name from configuration.
     *
     * @param array $toolConfig The tool configuration
     *
     * @return string The generated tool name
     */
    protected function generateToolName($toolConfig): string
    {
        $appSlug = $toolConfig->app_slug ?? 'unknown';
        $nodeId = $toolConfig->node_id ?? 'unnamed_tool';

        return $appSlug . '_' . $nodeId;
    }

    private static function storeToolSchemasInNodeData($node, array $schemas): void
    {
        $flowNode = FlowNode::findOne(['id' => $node->id]);
        if (!$flowNode) {
            return;
        }

        $encoded = JSON::encode($schemas);

        if (\function_exists('gzcompress')) {
            $encoded = base64_encode(gzcompress($encoded));
        }

        $data = $flowNode->data ? JSON::decode(JSON::encode($flowNode->data), true) : [];
        $data['schemas'] = $encoded;
        $flowNode->update(['data' => $data]);
        $flowNode->save();
    }

    private static function isToolsToIncludeAll($fieldMapping): bool
    {
        $fieldMappingData = JSON::decode(JSON::encode($fieldMapping), true);

        if (!\is_array($fieldMappingData)) {
            return false;
        }

        $configs = $fieldMappingData['configs'] ?? [];
        $toolsToInclude = $configs['tools-to-include']['value'] ?? null;

        return $toolsToInclude === 'all';
    }

    private static function decompressSchemas($cached): ?array
    {
        if (empty($cached) || !\is_string($cached)) {
            return null;
        }

        if (\function_exists('gzuncompress')) {
            $decoded = base64_decode($cached, true);
            if ($decoded !== false) {
                $uncompressed = @gzuncompress($decoded);
                if ($uncompressed !== false) {
                    return JSON::maybeDecode($uncompressed, true) ?: null;
                }
            }
        }

        $result = JSON::maybeDecode($cached, true);

        return \is_array($result) ? $result : null;
    }

    /**
     * Fix tool schemas loaded from cache for JSON encoding (e.g. OpenAI tools[].function.parameters).
     *
     * Associative decoding turns JSON objects into PHP arrays, but empty objects become [].
     * Encoding then emits [] instead of {}, while APIs expect parameters as a JSON object.
     * Recursively promote ambiguous [] to stdClass where JSON Schema uses objects; keep []
     * only for true JSON arrays (required, enum, combinators, etc.).
     *
     * @param array $schemas Tool schema list as returned from decompressSchemas
     *
     * @return array Schemas safe to pass through wp_json_encode
     */
    private static function normalizeToolSchemasAfterCacheDecode(array $schemas): array
    {
        foreach ($schemas as $i => $tool) {
            if (($tool['type'] ?? '') !== 'function' || !isset($tool['function']['parameters'])) {
                continue;
            }
            $schemas[$i]['function']['parameters'] = self::normalizeJsonSchemaValueAfterAssocDecode(
                $tool['function']['parameters'],
                null
            );
        }

        return $schemas;
    }

    /**
     * Normalize a decoded JSON Schema fragment so empty objects survive json_encode.
     *
     * @param mixed $value Decoded JSON value
     */
    private static function normalizeJsonSchemaValueAfterAssocDecode($value, ?string $parentKey)
    {
        if ($value === null) {
            return (object) [];
        }

        if (\is_object($value)) {
            foreach (get_object_vars($value) as $k => $v) {
                $value->{$k} = self::normalizeJsonSchemaValueAfterAssocDecode($v, (string) $k);
            }

            return $value;
        }

        if (!\is_array($value)) {
            return $value;
        }

        if ($value === []) {
            $jsonArrayKeys = ['required', 'enum', 'allOf', 'anyOf', 'oneOf', 'prefixItems'];

            if ($parentKey !== null && \in_array($parentKey, $jsonArrayKeys, true)) {
                return [];
            }

            return (object) [];
        }

        if (Utility::isSequentialArray($value)) {
            $normalized = [];
            foreach ($value as $item) {
                $normalized[] = self::normalizeJsonSchemaValueAfterAssocDecode($item, null);
            }

            return $normalized;
        }

        $normalized = [];
        foreach ($value as $k => $v) {
            $normalized[$k] = self::normalizeJsonSchemaValueAfterAssocDecode($v, (string) $k);
        }

        return $normalized;
    }

    private static function convertMcpToolsToSchemas(array $mcpTools, array $selectedTools = [], array $exceptSelectedTools = [], $toolNodeId = null): array
    {
        $schemas = [];
        foreach ($mcpTools as $tool) {
            $toolName = $tool->name ?? 'unnamed_tool';

            if (!empty($selectedTools) && !\in_array($toolName, $selectedTools, true)) {
                continue;
            }

            if (!empty($exceptSelectedTools) && \in_array($toolName, $exceptSelectedTools, true)) {
                continue;
            }

            $parameters = $tool->inputSchema ?? (object) [];

            if (empty($parameters->properties)) {
                $parameters = (object) [];
            }

            $schemas[] = [
                'type'     => 'function',
                'function' => [
                    'name'        => $toolName . '_' . $toolNodeId,
                    'description' => $tool->description ?? 'No description provided',
                    'parameters'  => $parameters
                ],
            ];
        }

        return $schemas;
    }

    private function getModelDefinedMappings(array $data): array
    {
        $result = [];

        foreach ($data as $groupKey => $groupData) {
            if (!isset($groupData['value']) || !\is_array($groupData['value'])) {
                continue;
            }

            foreach ($groupData['value'] as $fieldGroup) {
                $fieldName = null;
                $isModelDefined = false;

                foreach ($fieldGroup as $field) {
                    if (!empty($field['modelDefined'])) {
                        $isModelDefined = true;
                    }
                    if (
                        isset($field['value'])
                        && \is_string($field['value'])
                        && empty($field['modelDefined'])
                    ) {
                        $fieldName = $field['value'];
                    }
                }

                if ($isModelDefined && $fieldName) {
                    $result[] = "{$groupKey}.{$fieldName}";
                }
            }
        }

        return $result;
    }

    /**
     * Create parameter information from field data.
     *
     * @param string $fieldKey   The field key/name
     *
     * @return array Parameter info with 'property' and 'required' keys
     */
    private function createParameterInfo(string $fieldKey): array
    {
        return [
            'property' => [
                'type'        => 'string',
                'description' => "The {$fieldKey} parameter",
            ],
            'required' => false, // Can be customized based on field configuration
        ];
    }

    /**
     * Get description from tool configuration.
     *
     * @param mixed $toolConfig
     *
     * @return string The tool description
     */
    private function getDescription($toolConfig): string
    {
        return $toolConfig->description ? MixInputHandler::replaceMixTagValue($toolConfig->description) : 'No description provided';
    }

    /**
     * Recursively generate JSON Schema from a value.
     *
     * @param mixed $value The value to generate schema for
     *
     * @return array The schema definition
     */
    private function generateSchemaFromValue($value): array
    {
        if (\is_null($value)) {
            return ['type' => 'null'];
        }

        if (\is_bool($value)) {
            return ['type' => 'boolean'];
        }

        if (\is_int($value)) {
            return ['type' => 'integer'];
        }

        if (\is_float($value)) {
            return ['type' => 'number'];
        }

        if (\is_string($value)) {
            return ['type' => 'string'];
        }

        if (\is_array($value)) {
            // Check if it's a sequential array (list) or associative array (object)
            if (Utility::isSequentialArray($value)) {
                return $this->generateArraySchema($value);
            }

            return $this->generateObjectSchema($value);
        }

        return ['type' => 'string'];
    }

    /**
     * Generate schema for an array type.
     *
     * @param array $array The array to generate schema for
     *
     * @return array The array schema
     */
    private function generateArraySchema(array $array): array
    {
        if (empty($array)) {
            return [
                'type'  => 'array',
                'items' => ['type' => 'string'],
            ];
        }

        // Use the first element to determine the items schema
        $firstElement = reset($array);
        $itemsSchema = $this->generateSchemaFromValue($firstElement);

        return [
            'type'  => 'array',
            'items' => $itemsSchema,
        ];
    }

    /**
     * Generate schema for an object type.
     *
     * @param array $object The associative array to generate schema for
     *
     * @return array The object schema
     */
    private function generateObjectSchema(array $object): array
    {
        $properties = [];
        $required = [];

        foreach ($object as $key => $value) {
            $properties[$key] = $this->generateSchemaFromValue($value);
            $required[] = $key;
        }

        return [
            'type'                 => 'object',
            'properties'           => $properties,
            'required'             => $required,
            'additionalProperties' => false,
        ];
    }
}
