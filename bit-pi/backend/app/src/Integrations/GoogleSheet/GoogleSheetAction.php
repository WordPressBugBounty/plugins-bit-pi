<?php

namespace BitApps\Pi\src\Integrations\GoogleSheet;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Integrations\GoogleSheet\Helpers\GoogleSheetCommons;
use BitApps\Pi\src\Interfaces\ActionInterface;
use InvalidArgumentException;

class GoogleSheetAction implements ActionInterface
{
    private NodeInfoProvider $nodeInfoProvider;

    private GoogleSheetCommons $commons;

    private GoogleSpreadsheetService $spreadsheetService;

    private GoogleSheetService $sheetService;

    private GoogleRowService $rowService;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeSheetAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeMachine(string $machineSlug, array $configs, array $fieldMapData): array
    {
        $repeaters = $this->nodeInfoProvider->getFieldMapRepeaters('row-data.value', false, false);

        $mappedColumnValue = [];
        foreach ($repeaters as $repeater) {
            $mappedColumnValue[$this->commons->excelColumnToIndex($repeater['column'])] = $repeater['value'] ?? '';
        }

        $title = $fieldMapData['title'] ?? '';
        $limit = $fieldMapData['limit'] ?? 10;
        $spreadsheetId = $configs['spreadsheet-id']['value'] ?? null;
        $sheetTitle = $configs['sheet-title']['value'] ?? null;

        switch ($machineSlug) {
            case 'createSpreadsheet':
                return $this->spreadsheetService->createSpreadsheet($fieldMapData);

            case 'findSpreadsheets':
                return $this->spreadsheetService->findSpreadsheets($title, $limit);

            case 'deleteSpreadsheet':
                return $this->spreadsheetService->deleteSpreadsheet($spreadsheetId);

            case 'createSheet':
                return $this->sheetService->createSheet($spreadsheetId, $fieldMapData);

            case 'findWorksheet':
                $exactMatch = $fieldMapData['exactMatch'] ?? false;

                return $this->sheetService->findWorksheet($spreadsheetId, $title, $exactMatch);

            case 'copySheet':
                return $this->sheetService->copySheet($configs['spreadsheet-id']['value'] ?? null, $configs['sheet-title']['value'] ?? null, $fieldMapData);

            case 'deleteSheet':
                return $this->sheetService->deleteSheet($spreadsheetId, $sheetTitle);

            case 'clearSheet':
                return $this->sheetService->clearSheet($spreadsheetId, $sheetTitle, $configs['is-first-row-headers']['value'] ?? false);

            case 'exportSheet':
                return $this->sheetService->exportSheet($spreadsheetId, $sheetTitle, $configs['format']['value'] ?? 'csv');

            case 'addRow':
                return $this->rowService->addRow($configs, $mappedColumnValue);

            case 'appendOrUpdateRow':
                return $this->rowService->appendOrUpdateRow($configs, $mappedColumnValue);

            case 'updateRow':
                return $this->rowService->updateWorksheetRow($spreadsheetId, $sheetTitle, $fieldMapData, $mappedColumnValue);

            case 'deleteRow':
                return $this->rowService->deleteRow($spreadsheetId, $sheetTitle, $fieldMapData['rowId'] ?? null);

            case 'getSingleRowById':
                return $this->rowService->getRowByNumber($spreadsheetId, $sheetTitle, $fieldMapData['rowId'] ?? null);

            case 'getAllRows':
                return $this->rowService->getRows($spreadsheetId, $sheetTitle);

            case 'createColumn':
                return $this->rowService->createColumn($spreadsheetId, $sheetTitle, $fieldMapData['columnName'] ?? '', $fieldMapData['columnIndex'] ?? 0);

            case 'onNewSheet':
                return $this->sheetService->getWorksheets($spreadsheetId);

            case 'onNewSpreadsheet':
                return $this->spreadsheetService->getSpreadsheets();

            case 'onRowAdded':
                return $this->rowService->getRow($configs);

            case 'onRowAddedOrUpdated':
                return $this->rowService->getRows($spreadsheetId, $sheetTitle);

            default:
                throw new InvalidArgumentException(esc_html("Unknown action: {$machineSlug}"));
        }
    }

    private function executeSheetAction(): array
    {
        $configs = $this->nodeInfoProvider->getFieldMapConfigs();
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();
        $fieldMapData = $this->nodeInfoProvider->getFieldMapData();

        $connectionId = $configs['connection-id']['value'] ?? $configs['connection-id'];
        $accessToken = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::OAUTH2,
            $connectionId
        )->setRefreshTokenUrl('https://oauth2.googleapis.com/token')->getAccessToken();

        if (\is_array($accessToken)) {
            return [
                'response'    => $accessToken,
                'payload'     => [],
                'status_code' => 401
            ];
        }

        $headers = [
            'Authorization' => $accessToken,
            'Content-Type'  => 'application/json',
        ];

        $this->commons = new GoogleSheetCommons(new HttpClient(['headers' => $headers]));
        $this->spreadsheetService = new GoogleSpreadsheetService($this->commons);
        $this->sheetService = new GoogleSheetService($this->commons);
        $this->rowService = new GoogleRowService($this->commons);

        return $this->executeMachine($machineSlug, $configs, $fieldMapData);
    }
}
