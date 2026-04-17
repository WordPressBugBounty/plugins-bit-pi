<?php

namespace BitApps\Pi\Services;

use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\src\Abstracts\AbstractPollingTrigger;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

class Polling
{
    /**
     * Compare two multidimensional arrays and detect changes intelligently.
     *
     * Identifies new records and updated records based on a unique identifier field.
     * Optionally returns only one type of change based on the $pollingType parameter.
     *
     * @param array  $oldResponse previous dataset from the last polling
     * @param array  $newResponse current dataset from the latest polling
     * @param string $idField     field name used as the unique identifier (default: 'id')
     * @param string $pollingType type of polling: AbstractPollingTrigger::TYPE_NEW or TYPE_UPDATED
     *
     * @return array|false returns the requested type of changes (array of items) or false if no changes detected
     */
    public function detectNewOrUpdatedData($oldResponse, $newResponse, $idField = 'id', $pollingType = AbstractPollingTrigger::TYPE_NEW)
    {
        if (!\is_array($oldResponse) || !\is_array($newResponse)) {
            return false;
        }

        $changes = [
            AbstractPollingTrigger::TYPE_NEW     => [],
            AbstractPollingTrigger::TYPE_UPDATED => [],
        ];

        $oldMap = [];

        foreach ($oldResponse as $item) {
            if (isset($item[$idField])) {
                $oldMap[$item[$idField]] = $item;
            }
        }

        foreach ($newResponse as $item) {
            if (!isset($item[$idField])) {
                $changes[AbstractPollingTrigger::TYPE_NEW][] = $item;

                continue;
            }

            $itemId = $item[$idField];

            if (!isset($oldMap[$itemId])) {
                $changes[AbstractPollingTrigger::TYPE_NEW][] = $item;
            } elseif (JSON::encode($oldMap[$itemId]) !== JSON::encode($item)) {
                $changes[AbstractPollingTrigger::TYPE_UPDATED][] = [
                    'old' => $oldMap[$itemId],
                    'new' => $item
                ];
            }
        }

        if (empty($changes[AbstractPollingTrigger::TYPE_NEW]) && empty($changes[AbstractPollingTrigger::TYPE_UPDATED])) {
            return false;
        }

        return $changes[$pollingType];
    }

    public static function deletePollingData($flowId)
    {
        $indexPosition = 1;

        $nodeId = $flowId . '-' . $indexPosition;

        $optionKey = 'poll_response_' . $nodeId;

        if (Config::getOption($optionKey)) {
            Config::deleteOption($optionKey);
        }
    }
}
