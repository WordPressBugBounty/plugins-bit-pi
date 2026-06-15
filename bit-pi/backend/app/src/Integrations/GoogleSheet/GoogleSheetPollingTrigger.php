<?php

namespace BitApps\Pi\src\Integrations\GoogleSheet;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\src\Abstracts\AbstractPollingTrigger;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Integrations\GoogleSheet\Helpers\GoogleSheetCommons;

class GoogleSheetPollingTrigger extends AbstractPollingTrigger
{
    private string $poolingUniqueFieldName = '_row_number';

    private NodeInfoProvider $nodeInfoProvider;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function poll(): array
    {
        $configs = $this->nodeInfoProvider->getFieldMapConfigs();
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();
        $connectionId = $configs['connection-id']['value'] ?? $configs['connection-id'];
        $accessToken = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::OAUTH2,
            $connectionId
        )->setRefreshTokenUrl('https://oauth2.googleapis.com/token')->getAccessToken();

        if (!\is_string($accessToken)) {
            return ['status' => 'error', 'output' => $accessToken, 'input' => []];
        }

        $headers = [
            'Authorization' => $accessToken,
            'Content-Type'  => 'application/json',
        ];

        $commons = new GoogleSheetCommons(new HttpClient(['headers' => $headers]));
        $sheetService = new GoogleSheetService($commons);
        $spreadsheetService = new GoogleSpreadsheetService($commons);
        $rowService = new GoogleRowService($commons);

        switch ($machineSlug) {
            case 'onNewSheet':
                $spreadsheetId = $configs['spreadsheet-id']['value'] ?? $configs['spreadsheet-id'] ?? '';
                $response = $sheetService->getWorksheets($spreadsheetId);

                break;

            case 'onNewSpreadsheet':
                $response = $spreadsheetService->getSpreadsheets();

                break;

            case 'onRowAddedOrUpdated':
                $spreadsheetId = $configs['spreadsheet-id']['value'] ?? $configs['spreadsheet-id'] ?? '';
                $sheetTitle = $configs['sheet-title']['value'] ?? $configs['sheet-title'] ?? '';
                $response = $rowService->getRows($spreadsheetId, $sheetTitle);
                $triggerColumn = $configs['column-to-match-on']['value'] ?? $configs['column-to-match-on'] ?? 'all_columns';
                $rows = $response['response'] ?? [];
                foreach ($rows as &$row) {
                    $hashData = $row;
                    if ($triggerColumn !== 'all_columns') {
                        $hashData = $row[$triggerColumn] ?? $row;
                    }
                    $row['_row_hash'] = md5(JSON::encode($hashData));
                }
                $response['response'] = $rows;

                break;

            default:
                $response = $rowService->getRow($configs);

                break;
        }

        $status = $response['status'] ?? (isset($response['response']) ? 'success' : 'error');
        $output = JSON::decode(JSON::encode($response['response'] ?? []), true) ?? [];

        return [
            'status' => $status,
            'output' => $output,
            'input'  => $response['payload'] ?? [],
        ];
    }

    public function getUniquePollingFieldName(): string
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();

        switch ($machineSlug) {
            case 'onNewSheet':
                return 'sheetId';

            case 'onNewSpreadsheet':
                return 'id';

            case 'onRowAddedOrUpdated':
                return '_row_hash';

            default:
                return $this->poolingUniqueFieldName;
        }
    }
}
