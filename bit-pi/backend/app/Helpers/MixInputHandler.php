<?php

namespace BitApps\Pi\Helpers;

use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\src\Flow\GlobalNodeVariables;
use Exception;

if (!\defined('ABSPATH')) {
    exit;
}

class MixInputHandler
{
    /**
     * Replace field with values.
     *
     * This function processes mixed input values, replacing specific tags or placeholders
     * with their respective values, based on the input type and associated configurations.
     *
     * @param mixed  $mixedInputValues the input value, which could be a JSON-encoded string,
     *                                 an array of mixed types, or a plain value
     * @param mixed $returnFormat
     *
     * @returnFormat specifies the format of the return value.
     * - If 'array-first-element', returns the first element of the array if the input is an array.
     * - If 'array', returns an array of processed values.
     * - If null or any other value, returns a concatenated string of processed values.
     *
     * @throws Exception Throws an exception if processing encounters critical errors (e.g., invalid paths or data).
     *
     * @return mixed Returns the processed value based on the $returnFormat. If 'null' or not specified,
     *               concatenates the processed values. If 'array', returns an array of processed values.
     *
     * Processing Details:
     * - **Variable Type**: Retrieves data from a global node response based on `nodeId` and `path`.
     * - **String Type**: Directly appends the string value to the result.
     * - **Array Type**: Fetches a value from the specified index and path in the node response.
     * - **Pro Plugin Check**: If the Pro plugin is active, applies additional filters to the input values.
     */
    public static function replaceMixTagValue($mixedInputValues, $returnFormat = null)
    {
        $nodeResponseData = GlobalNodeVariables::getInstance()->getAllNodeResponse();

        $decodedInputData = JSON::maybeDecode($mixedInputValues, true);

        if ($decodedInputData === null || !\is_array($decodedInputData) || !self::isMixInput($decodedInputData)) {
            return $mixedInputValues;
        }

        $values = [];

        foreach ($decodedInputData as $item) {
            $item = (array) $item;

            switch ($item['type']) {
                case 'variable':
                    global $nodeIndexPosition;

                    if ($nodeIndexPosition !== null && isset($nodeIndexPosition[$item['nodeId']])) {
                        $index = $nodeIndexPosition[$item['nodeId']];
                        $platformValues = empty($nodeResponseData[$item['nodeId']]) ? [] : $nodeResponseData[$item['nodeId']][$index];
                    } else {
                        $platformValues = empty($nodeResponseData[$item['nodeId']]) ? [] : $nodeResponseData[$item['nodeId']];
                    }

                    if (!empty($platformValues)) {
                        $pathValue = Utility::getValueFromPath($platformValues, $item['path']);

                        $values[] = $pathValue;
                    }

                    break;

                case 'string':
                    $values[] = $item['value'];

                    break;

                case 'array':
                    $value = self::getValueFromIndexPath($item['path'], $item['nodeId']);

                    $values[] = \is_array($value) ? '' : $value;

                    break;

                default:
                    break;
            }

            if (is_plugin_active(Config::PRO_PLUGIN_SLUG . '/' . Config::PRO_PLUGIN_SLUG . '.php')) {
                $value = apply_filters(Config::VAR_PREFIX . 'mix_tag_input', $values, $item);

                if (!empty($value)) {
                    $values[] = $value;
                }
            }
        }


        return self::processValues($values, $returnFormat);
    }

    public static function getValueFromIndexPath($mixInputs, $nodeId)
    {
        $allNodeResponses = GlobalNodeVariables::getInstance()->getAllNodeResponse();

        if (!isset($allNodeResponses[$nodeId])) {
            return '';
        }

        $nodeData = $allNodeResponses[$nodeId];

        global $nodeIndexPosition;

        if ($nodeIndexPosition !== null && isset($nodeIndexPosition[$nodeId])) {
            $index = $nodeIndexPosition[$nodeId];
            $nodeData = $nodeData[$index] ?? $nodeData;
        }

        $indexPath = '';

        foreach ($mixInputs as $mixInput) {
            $mixInput = (array) $mixInput;

            if ($mixInput['type'] === 'obj_key') {
                $indexPath .= $mixInput['path'] . '.';
            } elseif ($mixInput['type'] === 'array_index') {
                $path = self::replaceMixTagValue($mixInput['value']) . '.';
                $indexPath .= $path;
            }
        }

        if (substr($indexPath, -1) === '.') {
            $indexPath = substr($indexPath, 0, -1);
        }

        return Utility::getValueFromPath($nodeData, $indexPath);
    }

    /**
     * Generate payload with field map.
     *
     * @param array $fieldMap
     *
     * @return array
     */
    public static function processData($fieldMap)
    {
        $payload = [];

        foreach ($fieldMap as $fieldPair) {
            $keys = explode('.', $fieldPair['path']);

            if (empty($fieldPair['value']) && !is_numeric($fieldMap['value'])) {
                continue;
            }

            $mixedInputValue = static::replaceMixTagValue($fieldPair['value']);

            static::assignValueToKey($payload, $keys, $mixedInputValue);
        }

        return $payload;
    }

    public static function processRepeaters($data, $isArrayAssociative, $isArrayColumn, $keyColumnName, $valueColumnName)
    {
        $output = [];

        foreach ($data as $items) {
            $itemArray = [];

            foreach ($items as $item) {
                if (isset($item['key'], $item['value'])) {
                    $key = static::replaceMixTagValue($item['key']);

                    $value = static::replaceMixTagValue($item['value']);

                    $itemArray[$key] = \is_array($value) ? '' : $value;
                }
            }

            if ($isArrayAssociative === true) {
                $output = array_merge($output, $itemArray);
            } else {
                $output[] = $itemArray;
            }
        }

        if ($isArrayColumn === true) {
            return array_column($output, $valueColumnName, $keyColumnName);
        }

        return $output;
    }

    public static function processConfigs($data)
    {
        // if data is string then showing warning thats why added this condition
        if (!is_iterable($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            $data[$key] = \is_array($value) ? static::processConfigs($value) : static::replaceMixTagValue($value);
        }

        return $data;
    }

    /**
     * Process and transform input values based on the return type.
     *
     * This function evaluates the provided values and processes them depending on
     * the specified return type. If there is only one value and it's an array,
     * the function can return it directly if `$returnFormat` is 'array'.
     * Otherwise, it transforms all elements in the array, converting arrays to the string "array",
     * objects to the string "object", and concatenating everything into a single string.
     *
     * @param array  $values     An array of values to be processed. Values can be arrays, objects, or scalars.
     * @param mixed $returnFormat
     *
     * @return mixed Returns:
     *               - If `$returnFormat` is 'array-first-element' and the input array contains a single array, that array is returned.
     *               - If `$returnFormat` is 'array', returns an array of processed values.
     *               - Otherwise, returns a concatenated string representation of the processed values.
     *
     * Processing Details:
     * - If `$values` has only one element and it's an array, and `$returnFormat` is 'array', that array is returned directly.
     * - Otherwise, all elements in `$values` are processed as follows:
     *   - Arrays are replaced with the string "array".
     *   - Objects are replaced with the string "object".
     *   - Scalars (e.g., strings, numbers) remain unchanged.
     * - Finally, all processed values are concatenated into a single string.
     */
    private static function processValues($values, $returnFormat)
    {
        if (\count($values) === 1 && \is_array($values[0]) && $returnFormat === 'array-first-element') {
            return $values[0];
        }

        $processedValues = array_map(
            function ($value) {
                if (\is_array($value)) {
                    return 'array';
                }

                if (\is_object($value)) {
                    return 'object';
                }

                return $value;
            },
            $values
        );

        if ($returnFormat === 'array') {
            return $values;
        }


        return self::checkAndTransformScalarValue($processedValues);
    }

    /**
     * Assign value to key.
     *
     * @param array $payload
     * @param array $keys
     * @param mixed $value
     */
    private static function assignValueToKey(&$payload, $keys, $value)
    {
        $currentKey = array_shift($keys);

        if (\count($keys) === 0) {
            $payload[$currentKey] = $value;
        } else {
            if (!isset($payload[$currentKey])) {
                $payload[$currentKey] = [];
            }

            static::assignValueToKey($payload[$currentKey], $keys, $value);
        }
    }

    /**
     * Checks the scalar types present in the given array and transforms the value accordingly.
     *
     * - If all values are `bool`, it returns the combined boolean representation.
     * - If all values are `int`, it returns the combined integer representation.
     * - If all values are `float`, it returns the combined float representation.
     * - Otherwise, it returns the concatenated string representation of the values.
     *
     * @param array $processedValues an array of scalar values to check and transform
     *
     * @return bool|float|int|string the transformed scalar value
     */
    private static function checkAndTransformScalarValue(array $processedValues)
    {
        if ($processedValues === []) {
            return '';
        }

        $value = implode('', $processedValues);

        if (array_filter($processedValues, 'is_bool') === $processedValues) {
            return (bool) $value;
        }

        if (array_filter($processedValues, 'is_int') === $processedValues) {
            return (int) $value;
        }

        if (array_filter($processedValues, 'is_float') === $processedValues) {
            return (float) $value;
        }

        return $value;
    }

    private static function isMixInput($values)
    {
        if (\is_array($values)) {
            if (!isset($values[0])) {
                return false;
            }
            $firstElement = (array) $values[0];

            return \is_array($firstElement) && \array_key_exists('type', $firstElement);
        }

        return false;
    }
}
