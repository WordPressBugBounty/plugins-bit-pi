<?php

namespace BitApps\Pi\src\Integrations\GoogleSheet;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\src\Abstracts\AbstractPollingTrigger;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Integrations\GoogleSheet\Helpers\Common;

class GoogleSheetPollingTrigger extends AbstractPollingTrigger
{
    public const BASE_URL = 'https://sheets.googleapis.com/v4';

    public const ADD_ROW = 'addRow';

    public const APPEND_OR_UPDATE_ROW = 'appendOrUpdateRow';

    private const POLLING_UNIQUE_FIELD_NAME = '_row_number';

    private NodeInfoProvider $nodeInfoProvider;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function poll(): array
    {
        $configs = $this->nodeInfoProvider->getFieldMapConfigs();

        $machineSlug = $this->nodeInfoProvider->getMachineSlug();

        $headers = Common::getAuthorizationHeader($configs['connection-id']['value']);

        $googleSheetRow = new GoogleSheetsRow(new HttpClient(['headers' => $headers]), static::BASE_URL);

        if ($machineSlug === 'onRowAdded') {
            $response = $googleSheetRow->getRow($configs);
        }

        if (empty($response) || !isset($response['response'])) {
            return [
                'status'  => 'error',
                'output'  => [],
                'input'   => $response['payload'] ?? [],
                'message' => $response['message'] ?? 'Unknown error occurred while fetching rows.',
            ];
        }

        return [
            'status' => 'success',
            'output' => $response['response'] ?? [],
            'input'  => $response['payload'] ?? [],
        ];
    }

    public function getPollingUniqueFieldName(): string
    {
        return self::POLLING_UNIQUE_FIELD_NAME;
    }
}
