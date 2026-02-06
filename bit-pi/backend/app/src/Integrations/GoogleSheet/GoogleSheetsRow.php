<?php

namespace BitApps\Pi\src\Integrations\GoogleSheet;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use Exception;

final class GoogleSheetsRow
{
    private $http;

    private $baseUrl;

    private $configs;

    /**
     * GoogleSheetService constructor.
     *
     * @param mixed $httpClient
     * @param string $baseUrl
     */
    public function __construct($httpClient, $baseUrl)
    {
        $this->http = $httpClient;
        $this->baseUrl = $baseUrl;
    }

    public function createRow($configs, $data)
    {
        if (\count($data) === 0) {
            return ['response' => 'No columns added! Please add some columns and map the fields.', 'payload' => ''];
        }

        $spreadsheetsId = empty($configs['spreadsheet-id']['value']) ? '' : $configs['spreadsheet-id']['value'];
        $workSheetName = empty($configs['sheet-title']['value']) ? '' : $configs['sheet-title']['value'];
        $valueInputOption = empty($configs['value-input-option']['value']) ? 'USER_ENTERED' : $configs['value-input-option']['value'];
        $insertDataOption = empty($configs['insert-data-option']['value']) ? 'INSERT_ROWS' : $configs['insert-data-option']['value'];

        $minIndex = min(array_keys($data));
        $maxIndex = max(array_keys($data));
        $minRangeLetter = $this->columnIndexToLetter($minIndex);
        $maxRangeLetter = $this->columnIndexToLetter($maxIndex);

        $fetchedData = $this->fetchSheetData($spreadsheetsId, $workSheetName, "{$minRangeLetter}:{$maxRangeLetter}");

        $insertAbleRow = 1;
        if (property_exists($fetchedData, 'values') && \count($fetchedData->values)) {
            $insertAbleRow = \count($fetchedData->values) + 1;
        }

        $range = "{$workSheetName}!{$minRangeLetter}{$insertAbleRow}:{$maxRangeLetter}{$insertAbleRow}";
        $url = "{$this->baseUrl}/spreadsheets/{$spreadsheetsId}/values/{$range}:append?valueInputOption={$valueInputOption}&insertDataOption={$insertDataOption}";

        $values = [];
        for ($i = $minIndex; $i <= $maxIndex; ++$i) {
            $values[] = $data[$i] ?? null;
        }

        $payload = [
            'range'          => $range,
            'majorDimension' => 'ROWS',
            'values'         => [$values]
        ];

        $response = $this->http->request($url, 'POST', JSON::encode($payload));

        return ['response' => $response, 'payload' => $payload];
    }

    public function appendOrUpdateRow($configs, $data)
    {
        $spreadsheetsId = empty($configs['spreadsheet-id']['value']) ? '' : $configs['spreadsheet-id']['value'];

        $workSheetName = empty($configs['sheet-title']['value']) ? '' : $configs['sheet-title']['value'];

        $fetchedData = $this->fetchSheetData($spreadsheetsId, $workSheetName);
        $columnToMatch = $this->getColumnToMatchId($configs);

        $rowToUpdate = ['matchedRow' => -1, 'values' => []];
        if (\array_key_exists($columnToMatch, $data) && property_exists($fetchedData, 'values') && \count($fetchedData->values)) {
            $rowToUpdate = $this->getRowToUpdate($fetchedData->values, $columnToMatch, $data[$columnToMatch]);
        }

        if ($rowToUpdate['matchedRow'] > 0) {
            $url = $this->baseUrl . '/spreadsheets/' . $spreadsheetsId . '/values:batchUpdate';
            $payload = $this->prepareDataForUpdate($workSheetName, $rowToUpdate['matchedRow'], $rowToUpdate['values'], $data);
            $response = $this->http->request($url, 'POST', JSON::encode($payload));

            return ['response' => $response, 'payload' => $payload];
        }

        return $this->createRow($configs, $data);
    }

    public function getRow($configs)
    {
        $spreadsheetsId = empty($configs['spreadsheet-id']['value']) ? '' : $configs['spreadsheet-id']['value'];

        $workSheetName = empty($configs['sheet-title']['value']) ? '' : $configs['sheet-title']['value'];


        if (empty($spreadsheetsId) || empty($workSheetName)) {
            return [
                'status'   => 'error',
                'message'  => 'Spreadsheet ID or Sheet Title is missing',
                'response' => []
            ];
        }

        try {
            // Fetch all data from the sheet
            $fetchedData = $this->fetchSheetData($spreadsheetsId, $workSheetName);

            if (!property_exists($fetchedData, 'values') || empty($fetchedData->values)) {
                return [
                    'status'   => 'success',
                    'message'  => 'No data found in the sheet',
                    'response' => [],
                    'count'    => 0
                ];
            }

            $rows = [];
            $headers = [];

            // Process each row
            foreach ($fetchedData->values as $rowIndex => $rowData) {
                if ($rowIndex === 0) {
                    // First row is headers
                    $headers = $rowData;

                    continue;
                }

                // Create associative array with headers as keys
                $row = [];
                foreach ($headers as $headerIndex => $header) {
                    $row[$header] = $rowData[$headerIndex] ?? '';
                }

                // Add row number for reference
                $row['_row_number'] = $rowIndex + 1;
                $rows[] = $row;
            }

            return ['response' => $rows, 'payload' => ['sheet_name' => $workSheetName, 'id' => $spreadsheetsId]];
        } catch (Exception $e) {
            return [
                'status'   => 'error',
                'message'  => 'Error fetching rows: ' . $e->getMessage(),
                'response' => []
            ];
        }
    }

    /**
     * Set configuration for the getRow method.
     *
     * @param mixed $configs
     */
    public function setConfigs($configs)
    {
        $this->configs = $configs;
    }

    private function getColumnToMatchId(array $configs)
    {
        return !empty($configs['column-to-match-on']['value']) && strpos($configs['column-to-match-on']['value'], ':') !== false ? explode(':', $configs['column-to-match-on']['value'])[0] : '';
    }

    private function fetchSheetData(string $spreadsheetId, string $workSheetName, ?string $range = null)
    {
        $cellRange = '';

        if ($range) {
            $cellRange = $range[0] === '!' ? $range : '!' . $range;
        }

        $url = $this->baseUrl . '/spreadsheets/' . $spreadsheetId . '/values/' . urlencode($workSheetName) . $cellRange;

        return $this->http->request(
            $url,
            'GET',
            [
                'valueRenderOption'    => 'FORMATTED_VALUE',
                'dateTimeRenderOption' => 'FORMATTED_STRING'
            ]
        );
    }

    private function getRowToUpdate(array $data, int $columnToMatch, string $valueToMatch): array
    {
        $matchedRow = -1;
        $values = [];
        foreach ($data as $rowId => $rowValues) {
            if (isset($rowValues[$columnToMatch]) && $rowValues[$columnToMatch] === $valueToMatch) {
                $values = $rowValues;
                $matchedRow = $rowId;

                break;
            }
        }

        return ['matchedRow' => $matchedRow, 'values' => $values];
    }

    private function prepareDataForUpdate(string $workSheetName, int $rowId, array $oldData, array $newData): array
    {
        $updatePayload = [];
        $oldLength = \count($oldData);
        $newLength = max(array_keys($newData)) + 1;
        $rowLength = max($oldLength, $newLength);

        for ($columnIndex = 0; $columnIndex < $rowLength; ++$columnIndex) {
            $oldValue = isset($oldData[$columnIndex]) ? $oldData[$columnIndex] : '';
            $newValue = isset($newData[$columnIndex]) ? $newData[$columnIndex] : $oldValue;

            if ($oldValue === $newValue) {
                continue;
            }

            $updatePayload[] = [
                'range'  => $workSheetName . '!' . $this->columnIndexToLetter($columnIndex) . ($rowId + 1),
                'values' => [[$newValue]]
            ];
        }

        return ['data' => $updatePayload, 'valueInputOption' => 'USER_ENTERED'];
    }

    private function columnIndexToLetter($columnIndex)
    {
        ++$columnIndex;
        $letter = '';
        while ($columnIndex > 0) {
            --$columnIndex;
            $letter = \chr($columnIndex % 26 + 65) . $letter;
            $columnIndex = (int) ($columnIndex / 26);
        }

        return $letter;
    }
}
