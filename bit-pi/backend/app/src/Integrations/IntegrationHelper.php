<?php

namespace BitApps\Pi\src\Integrations;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPValidator\Validator;
use BitApps\Pi\Helpers\Node;
use BitApps\Pi\src\Flow\FlowExecutor;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use ReflectionClass;

class IntegrationHelper
{
    public static function handleFlowForForm($flows, $data, $value = null, $keyName = 'form-id', $valueType = 'int')
    {
        foreach ($flows as $flow) {
            $triggerNode = Node::getNodeInfoById($flow->id . '-1', $flow->nodes);

            if (!$triggerNode) {
                continue;
            }

            if ($value) {
                $nodeHelper = new NodeInfoProvider($triggerNode);

                $configuredFormValue = $nodeHelper->getFieldMapConfigs($keyName . '.value');

                if ($configuredFormValue !== 'any') {
                    if ($valueType === 'int') {
                        $value = (int) $value;
                        $configuredFormValue = (int) $configuredFormValue;
                    }

                    if ($configuredFormValue !== $value) {
                        continue;
                    }
                }
            }

            FlowExecutor::execute($flow, $data);
        }
    }

    public static function validateFieldMap($fieldMappings, $validationRules, $payload = null)
    {
        $validator = new Validator();
        $validation = $validator->make($fieldMappings, $validationRules);

        if ($validation->fails()) {
            return [
                'response'    => $validation->errors(),
                'payload'     => $payload ?? $fieldMappings,
                'status_code' => 400,
            ];
        }
    }

    /**
     * Resolve a service instance for the given action method.
     *
     * Detects the calling Action class via `debug_backtrace()`, then resolves:
     * - `$isSingleService`  → `{Caller}Action` → `{Caller}Service` class
     * - `!$isSingleService` → scans `{CallerDir}/Services/*Service.php`
     *
     * @param string           $machineSlug       action method name (mapped from machine slug)
     * @param NodeInfoProvider $nodeInfoProvider  field-map data and execution context
     * @param bool             $isSingleService   use single-service convention (Action → Service)
     *
     * @return null|object service instance, or null if none found
     */
    public static function getServiceInstanceByMachineSlug($machineSlug, $nodeInfoProvider, $isSingleService = false)
    {
        if ($isSingleService) {
            return self::getSingleServiceInstanceByMachineSlug($machineSlug, $nodeInfoProvider);
        }

        return self::getMultiServiceInstanceByMachineSlug($machineSlug, $nodeInfoProvider);
    }

    private static function getSingleServiceInstanceByMachineSlug($machineSlug, $nodeInfoProvider)
    {
        $callerClassInfo = self::getCallerClassInfo();

        if (empty($callerClassInfo)) {
            return;
        }

        $callerClass = $callerClassInfo['class'];
        $singleServiceClass = str_replace('Action', 'Service', $callerClass);

        if (!class_exists($singleServiceClass)) {
            return;
        }

        $serviceClassReflection = new ReflectionClass($singleServiceClass);
        if (!$serviceClassReflection->isInstantiable()) {
            return;
        }

        $serviceInstance = new $singleServiceClass($nodeInfoProvider);
        if (!\is_callable([$serviceInstance, $machineSlug])) {
            return;
        }

        return $serviceInstance;
    }

    private static function getMultiServiceInstanceByMachineSlug($machineSlug, $nodeInfoProvider)
    {
        $callerClassInfo = self::getCallerClassInfo();

        if (empty($callerClassInfo)) {
            return;
        }

        $namespace = $callerClassInfo['namespace'];
        $serviceDirectory = $callerClassInfo['serviceDirectory'];
        $serviceFiles = self::getServiceFiles($serviceDirectory);

        foreach ($serviceFiles as $serviceFile) {
            $fileName = $serviceFile['name'];
            $serviceOffset = -11;

            if (substr($fileName, $serviceOffset) !== 'Service.php') {
                continue;
            }

            $serviceClass = $namespace . '\\' . basename($fileName, '.php');

            if (!class_exists($serviceClass)) {
                continue;
            }

            $serviceClassReflection = new ReflectionClass($serviceClass);
            if (!$serviceClassReflection->isInstantiable()) {
                continue;
            }

            $serviceInstance = new $serviceClass($nodeInfoProvider);

            if (\is_callable([$serviceInstance, $machineSlug])) {
                return $serviceInstance;
            }
        }
    }

    private static function getCallerClassInfo()
    {
        $callerClassIndex = 3;
        $backtraceLimit = 4;

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $backtraceLimit);
        $callerClass = $trace[$callerClassIndex]['class'] ?? '';

        if (empty($callerClass)) {
            return;
        }

        $callerClassReflection = new ReflectionClass($callerClass);

        return [
            'class'            => $callerClass,
            'namespace'        => $callerClassReflection->getNamespaceName() . '\Services',
            'serviceDirectory' => \dirname($callerClassReflection->getFileName()) . '/Services'
        ];
    }

    private static function getServiceFiles($serviceDirectory)
    {
        global $wp_filesystem;

        if (empty($wp_filesystem)) {
            include_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if (!$wp_filesystem || !$wp_filesystem->is_dir($serviceDirectory)) {
            return [];
        }

        return $wp_filesystem->dirlist($serviceDirectory) ?: [];
    }
}
