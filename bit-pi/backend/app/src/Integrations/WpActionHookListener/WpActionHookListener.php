<?php

namespace BitApps\Pi\src\Integrations\WpActionHookListener;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Helpers\Node;
use BitApps\Pi\Services\FlowService;
use BitApps\Pi\src\Flow\FlowExecutor;

/**
 * This class is responsible for capturing the wp action hook data and executing the flow.
 *
 * It can be used only if there does not have any condition to execute the hook
 */
class WpActionHookListener
{
    public $appSlug;

    public $machineSlug;

    public $hookArgumentsNames;

    public function __construct(string $appSlug, string $machineSlug, array $hookArgumentsNames = [])
    {
        $this->appSlug = $appSlug;
        $this->machineSlug = $machineSlug;
        $this->hookArgumentsNames = $hookArgumentsNames;
    }

    public function captureHookData(...$arguments)
    {
        $flows = FlowService::exists($this->appSlug, $this->machineSlug);

        if (!$flows) {
            return;
        }

        $currentHookName = current_action();

        if ($this->isDuplicateHookCall($currentHookName)) {
            return;
        }

        foreach ($arguments as $key => $argument) {
            if (\is_object($argument) && class_exists(\get_class($argument))) {
                $arguments[$key] = json_decode(wp_json_encode($argument), true);
            }
        }

        if (\count($this->hookArgumentsNames) === \count($arguments)) {
            $arguments = array_combine($this->hookArgumentsNames, $arguments);
        }

        foreach ($flows as $flow) {
            $triggerNode = Node::getNodeInfoById($flow->id . '-1', $flow->nodes);

            if (!$triggerNode) {
                continue;
            }

            FlowExecutor::execute($flow, $arguments);
        }
    }

    private function isDuplicateHookCall($hookName)
    {
        $duplicateHookCallList = [
            'delete_post'
        ];

        if (\in_array($hookName, $duplicateHookCallList)) {
            return did_action($hookName) > 1;
        }

        return false;
    }
}
