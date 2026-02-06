<?php

namespace BitApps\Pi\src\Integrations\GoogleSheet;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Integrations\GoogleSheet\Helpers\Common;

class GoogleSheetTrigger
{
    public const BASE_URL = 'https://sheets.googleapis.com/v4';

    public const ADD_ROW = 'addRow';

    public const APPEND_OR_UPDATE_ROW = 'appendOrUpdateRow';

    private $poolingUniqueFieldName = '_row_number';

    private $pullingType = 'add_new';

    private NodeInfoProvider $nodeInfoProvider;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function pull()
    {
        $configs = $this->nodeInfoProvider->getFieldMapConfigs();

        $machineSlug = $this->nodeInfoProvider->getMachineSlug();

        $headers = Common::getAuthorizationHeader($configs['connection-id']['value']);

        $googleSheetRow = new GoogleSheetsRow(new HttpClient(['headers' => $headers]), static::BASE_URL);

        $repeaters = $this->nodeInfoProvider->getFieldMapRepeaters('row-data.value', false, false);

        $mappedColumnValue = [];

        foreach ($repeaters as $repeater) {
            $mappedColumnValue[Common::excelColumnToIndex($repeater['column'])] = $repeater['value'];
        }

        if ($machineSlug === 'onRowAdded') {
            $response = $googleSheetRow->getRow($configs, $mappedColumnValue);
        }

        return [
            'status' => $response['status'] ?? 'error',
            'output' => $response['response'] ?? [],
            'input'  => $response['payload'] ?? [],
        ];
    }

    public function getUniquePollingFieldName(): string
    {
        return $this->poolingUniqueFieldName;
    }
}
