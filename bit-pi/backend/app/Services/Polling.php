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
     * @param array  $newResponse current dataset from the latest polling, holding
     *                            records as either arrays or objects
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
            $itemId = $this->identifierOf($item, $idField);

            if ($itemId !== null) {
                $oldMap[$itemId] = $item;
            }
        }

        foreach ($newResponse as $item) {
            $itemId = $this->identifierOf($item, $idField);

            if ($itemId === null) {
                $changes[AbstractPollingTrigger::TYPE_NEW][] = $item;

                continue;
            }

            // A NEW-type cache holds only identifiers (see compactForCache), so whole
            // records are compared only for UPDATED polling.
            if (!isset($oldMap[$itemId])) {
                $changes[AbstractPollingTrigger::TYPE_NEW][] = $item;
            } elseif ($pollingType === AbstractPollingTrigger::TYPE_UPDATED && JSON::encode($oldMap[$itemId]) !== JSON::encode($item)) {
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

    /**
     * Reduce a poll result to what the next poll's diff compares against.
     *
     * A NEW-type diff only asks whether each record's identifier was seen before,
     * so only the identifier is cached instead of the whole record (email bodies,
     * full sheet rows). Each entry keeps the {idField: value} shape, so caches
     * written by older versions, which hold full records, are read the same way.
     * An UPDATED-type diff compares whole records and keeps them as they are.
     *
     * @param mixed  $records     records returned by the polling trigger
     * @param string $idField     field name used as the unique identifier
     * @param string $pollingType AbstractPollingTrigger::TYPE_NEW or TYPE_UPDATED
     *
     * @return mixed the identifiers to cache, or $records unchanged when they are not compacted
     */
    public function compactForCache($records, $idField = 'id', $pollingType = AbstractPollingTrigger::TYPE_NEW)
    {
        if (!\is_array($records) || $pollingType !== AbstractPollingTrigger::TYPE_NEW) {
            return $records;
        }

        $compact = [];

        foreach ($records as $item) {
            $itemId = $this->identifierOf($item, $idField);

            // A record without an identifier is treated as new on every poll
            // anyway, so caching it would change nothing.
            if ($itemId !== null) {
                $compact[] = [$idField => $itemId];
            }
        }

        return $compact;
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

    /**
     * The identifier of one polled record, or null when it carries none.
     *
     * A record is normally an array, but an integration that decodes its
     * response without the associative flag hands over stdClass instead, and
     * reading an array key off one of those is fatal rather than merely false.
     *
     * @param mixed  $item
     * @param string $idField
     *
     * @return mixed the identifier, or null when the record carries none
     */
    private function identifierOf($item, $idField)
    {
        $record = \is_object($item) ? (array) $item : $item;

        if (!\is_array($record) || !isset($record[$idField])) {
            return;
        }

        return $record[$idField];
    }
}
