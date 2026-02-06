<?php

namespace BitApps\Pi\src\Tools\AiAgent\Schema;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Helpers\MixInputHandler;
use BitApps\Pi\Helpers\Node;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Flow\NodeInfoProvider;

/**
 * Universal AI tool schema generator.
 *
 * This class converts application tools into AI-compatible function schemas.
 * Works with all AI providers (OpenAI, Anthropic, Groq, etc.) using the
 * standard function calling format.
 */
class AIToolSchema
{
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

            if (empty($node)) {
                continue;
            }

            $schema = (new self())->createSchema($node);

            if (!empty($schema)) {
                $schemas[] = $schema;
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
        if ($formatType === 'text') {
            return [
                'type' => 'text',
            ];
        }

        if ($formatType === 'json_object') {
            return [
                'type' => 'json_object',
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
        // Determine parameter type based on field value

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
        $description = MixInputHandler::replaceMixTagValue($toolConfig->description ?? 'No description provided');

        // Process any mix input tags in the description
        return $description ?? 'No description provided';
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
