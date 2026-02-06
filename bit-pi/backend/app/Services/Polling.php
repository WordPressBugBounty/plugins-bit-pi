<?php

namespace BitApps\Pi\Services;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}

class Polling
{
    /**
     * Compare two multidimensional arrays and detect changes intelligently.
     *
     * Identifies new records and updated records based on a unique identifier field.
     * Optionally returns only one type of change based on the $actionType parameter.
     *
     * @param array       $oldResponse previous dataset from the last polling
     * @param array       $newResponse current dataset from the latest polling
     * @param string      $idField     field name used as the unique identifier (default: 'id')
     * @param string      $actionType  type of changes to return: 'new' for new items, 'updated' for modified items
     *
     * @return array|false returns the requested type of changes (array of items) or false if no changes detected
     */
    public function detectNewOrUpdatedData($oldResponse, $newResponse, $idField = 'id', $actionType = 'new')
    {
        $changes = [
            'new'     => [],
            'updated' => [],
        ];

        $oldMap = [];

        $newMap = [];

        foreach ($oldResponse as $item) {
            if (isset($item[$idField])) {
                $oldMap[$item[$idField]] = $item;
            }
        }

        foreach ($newResponse as $item) {
            if (!isset($item[$idField])) {
                // Items without ID are considered new
                $changes['new'][] = $item;

                continue;
            }

            $itemId = $item[$idField];

            $newMap[$itemId] = $item;

            if (!isset($oldMap[$itemId])) {
                // ID doesn't exist in old data - it's new
                $changes['new'][] = $item;
            } elseif (wp_json_encode($oldMap[$itemId]) !== wp_json_encode($item)) {
                // ID exists but content is different - it's modified
                $changes['updated'][] = [
                    'old' => $oldMap[$itemId],
                    'new' => $item
                ];
            }
        }

        if (empty($changes['new']) && empty($changes['updated'])) {
            return false;
        }

        return $changes[$actionType];
    }
}
