<?php

namespace BitApps\Pi\src\Tools\Schedule;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Model\Flow;
use BitApps\Pi\Model\FlowNode;
use BitApps\Pi\Services\Polling;
use BitApps\Pi\src\Flow\FlowExecutor;
use BitApps\Pi\src\Flow\NodeExecutor;

use BitApps\Pi\src\Flow\NodeInfoProvider;

/**
 * ScheduleTool.
 *
 * Handles the execution of scheduled flows in Bit Pi Pro.
 * This tool manages the triggering of flows based on scheduled events,
 * including both direct schedule triggers and polling-based triggers for external data sources.
 *
 * Features:
 * - Executes flows triggered by WordPress cron events
 * - Handles polling-based triggers for external APIs
 * - Manages data change detection for scheduled flows
 * - Supports both schedule and polling machine types
 * - Validates execution conditions (day of month, day of week)
 *
 * @since 1.7.0
 */
class ScheduleTool
{
    /**
     * Machine slug for schedule-based triggers.
     *
     * @var string
     */
    private const MACHINE_SLUG = 'schedule';

    /**
     * Handle scheduled flow trigger execution.
     *
     * This method is called by WordPress cron events to execute scheduled flows.
     * It handles both direct schedule triggers and polling-based triggers for external data sources.
     *
     * Process:
     * 1. Validates node ID and retrieves node information
     * 2. Checks execution conditions (day of month, day of week)
     * 3. For schedule triggers: executes flow directly
     * 4. For polling triggers: checks for data changes and executes flow with new data
     * 5. Manages response caching for change detection
     *
     * @param string $nodeId The unique identifier for the flow node
     * @param null|int $dayOfMonth Optional day of month for monthly schedules
     * @param null|int $day Optional day of week for weekly schedules (0=Sunday, 6=Saturday)
     * @param bool $isTestFlow Whether this is a test flow execution (bypasses day checks)
     */
    public function scheduleTriggerHandler($nodeId, $dayOfMonth = null, $day = null, $isTestFlow = false)
    {
        if (empty($nodeId)) {
            return;
        }

        $nodeInfo = FlowNode::where('node_id', $nodeId)
            ->first();

        if (!$nodeInfo) {
            return;
        }

        $appSlug = $nodeInfo->app_slug ?? null;

        $machineSlug = $nodeInfo->machine_slug ?? null;

        if ($dayOfMonth !== null && $dayOfMonth !== date('j') && !$isTestFlow) {
            return;
        }

        if ($day !== null && $day !== date('w') && !$isTestFlow) {
            return;
        }

        if (empty($appSlug) || empty($machineSlug)) {
            return;
        }

        $flowIdIndexPosition = 0;

        $flowId = explode('-', $nodeId)[$flowIdIndexPosition];

        $flow = Flow::with('nodes')->where('id', $flowId)->first();

        if (!$flow) {
            return;
        }

        if ($machineSlug === self::MACHINE_SLUG) {
            FlowExecutor::execute($flow, []);

            return;
        }

        $triggerApp = (new NodeExecutor())->isExistClass($appSlug, 'Trigger');

        if (!$triggerApp || !method_exists($triggerApp, 'pull')) {
            return;
        }

        $triggerApp = new $triggerApp(new NodeInfoProvider($nodeInfo));

        $pollingUniqueFieldName = 'id';

        if (method_exists($triggerApp, 'getUniquePollingFieldName')) {
            $pollingUniqueFieldName = $triggerApp->getUniquePollingFieldName();
        }

        $response = $triggerApp->pull()['output'] ?? [];

        if (empty($response)) {
            return;
        }

        $optionName = Config::VAR_PREFIX . 'pool_response_' . $nodeId;

        $storedResponse = Config::getOption($optionName);

        // If no previous response exists, cache current response and exit
        if (!$storedResponse) {
            $compressedResponse = base64_encode(gzcompress(wp_json_encode($response)));
            Config::updateOption($optionName, $compressedResponse);

            return;
        }

        $decompressedStoredResponse = gzuncompress(base64_decode($storedResponse));

        $decodedStoredResponse = JSON::maybeDecode($decompressedStoredResponse, true);

        $changeDetected = (new Polling())->detectNewOrUpdatedData($decodedStoredResponse, $response, $pollingUniqueFieldName);

        if (!$changeDetected) {
            return;
        }

        $newResponse = base64_encode(gzcompress(wp_json_encode($response)));

        Config::updateOption(Config::VAR_PREFIX . 'pool_response_' . $nodeId, $newResponse);

        foreach ($changeDetected as $newItem) {
            FlowExecutor::execute($flow, $newItem);
        }
    }
}
