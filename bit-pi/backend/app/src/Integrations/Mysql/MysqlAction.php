<?php

namespace BitApps\Pi\src\Integrations\Mysql;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;
use InvalidArgumentException;
use Throwable;

class MysqlAction implements ActionInterface
{
    private NodeInfoProvider $nodeInfoProvider;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        return $this->dispatch();
    }

    private function dispatch(): array
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();
        $configs = $this->nodeInfoProvider->getFieldMapConfigs();
        $fieldMapData = $this->nodeInfoProvider->getFieldMapData();

        $connectionId = $configs['connection-id']['value'] ?? $configs['connection-id'] ?? null;
        $table = $configs['table-name']['value'] ?? '';
        $matchColumn = $configs['match-column']['value'] ?? '';

        try {
            $service = MysqlService::fromConnection($connectionId);

            switch ($machineSlug) {
                case 'executeQuery':
                    return $service->executeQuery(
                        $fieldMapData['query'] ?? '',
                        $this->repeaterValues('query-params')
                    );

                case 'insertRow':
                    return $service->insertRow($table, $this->columnMap());

                case 'updateRow':
                    return $service->updateRow(
                        $table,
                        $this->columnMap(),
                        $matchColumn,
                        $fieldMapData['matchValue'] ?? ''
                    );

                case 'selectRows':
                    return $service->selectRows(
                        $table,
                        $fieldMapData['where'] ?? '',
                        $this->repeaterValues('where-params'),
                        $fieldMapData['limit'] ?? ''
                    );

                case 'deleteRows':
                    return $service->deleteRows($table, $matchColumn, $fieldMapData['matchValue'] ?? '');

                default:
                    throw new InvalidArgumentException(esc_html("Unknown MySQL machine slug: {$machineSlug}"));
            }
        } catch (Throwable $th) {
            return Utility::formatResponseData(
                500,
                ['machine' => $machineSlug],
                ['error'   => true, 'message' => $th->getMessage()]
            );
        }
    }

    /**
     * Associative [column => value] map from the column-data repeater.
     */
    private function columnMap(): array
    {
        return (array) $this->nodeInfoProvider->getFieldMapRepeaters('column-data.value', false, true, 'column', 'value');
    }

    /**
     * Ordered list of values from a single-field repeater (positional ? params).
     */
    private function repeaterValues(string $componentId): array
    {
        $rows = $this->nodeInfoProvider->getFieldMapRepeaters("{$componentId}.value", false, false);

        return array_map(static fn ($row) => $row['value'] ?? null, (array) $rows);
    }
}
