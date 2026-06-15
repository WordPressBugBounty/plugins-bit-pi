<?php

namespace BitApps\Pi\src\Integrations\GoogleSheet;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\src\Integrations\GoogleSheet\Helpers\GoogleSheetCommons;

/**
 * Service class for row and column level operations.
 */
final class GoogleRowService
{
    private GoogleSheetCommons $commons;

    public function __construct(GoogleSheetCommons $commons)
    {
        $this->commons = $commons;
    }

    /**
     * Append a row to a worksheet.
     */
    public function addRow(array $configs, array $mappedColumnValue): array
    {
        $spreadsheetId = $configs['spreadsheet-id']['value'] ?? '';
        $sheetTitle = $configs['sheet-title']['value'] ?? '';
        $valueInputOption = $configs['value-input-option']['value'] ?? 'USER_ENTERED';

        if (empty($mappedColumnValue)) {
            return [
                'response'    => 'No columns added! Please add some columns and map the fields.',
                'payload'     => '',
                'status_code' => 422
            ];
        }

        $minIndex = min(array_keys($mappedColumnValue));
        $maxIndex = max(array_keys($mappedColumnValue));
        $minLetter = $this->commons->columnIndexToLetter($minIndex);
        $maxLetter = $this->commons->columnIndexToLetter($maxIndex);

        $range = "{$sheetTitle}!{$minLetter}:{$maxLetter}";
        $url = GoogleSheetCommons::BASE_URL . "/spreadsheets/{$spreadsheetId}/values/{$range}:append?valueInputOption={$valueInputOption}&insertDataOption=INSERT_ROWS";

        $values = [];
        for ($i = $minIndex; $i <= $maxIndex; ++$i) {
            $values[] = $mappedColumnValue[$i] ?? null;
        }

        $payload = ['majorDimension' => 'ROWS', 'values' => [$values]];
        $response = $this->commons->getHttp()->request($url, 'POST', JSON::encode($payload));

        return [
            'response'    => $response,
            'payload'     => $payload,
            'status_code' => $this->commons->getHttp()->getResponseCode()
        ];
    }

    /**
     * Append or update a row by matching a column value.
     */
    public function appendOrUpdateRow(array $configs, array $mappedColumnValue): array
    {
        $spreadsheetId = $configs['spreadsheet-id']['value'] ?? '';
        $sheetTitle = $configs['sheet-title']['value'] ?? '';

        $fetched = $this->commons->fetchSheetData($spreadsheetId, $sheetTitle);
        $columnToMatch = $this->getColumnToMatchId($configs);

        $rowToUpdate = ['matchedRow' => -1, 'values' => []];
        if ($columnToMatch >= 0 && \array_key_exists($columnToMatch, $mappedColumnValue) && property_exists($fetched, 'values')) {
            $rowToUpdate = $this->getRowToUpdate($fetched->values, $columnToMatch, $mappedColumnValue[$columnToMatch]);
        }

        if ($rowToUpdate['matchedRow'] >= 0) {
            $url = GoogleSheetCommons::BASE_URL . '/spreadsheets/' . $spreadsheetId . '/values:batchUpdate';
            $payload = $this->prepareDataForUpdate($sheetTitle, $rowToUpdate['matchedRow'], $rowToUpdate['values'], $mappedColumnValue);
            $response = $this->commons->getHttp()->request($url, 'POST', JSON::encode($payload));

            return [
                'response'    => $response,
                'payload'     => $payload,
                'status_code' => $this->commons->getHttp()->getResponseCode()
            ];
        }

        return $this->addRow($configs, $mappedColumnValue);
    }

    /**
     * Update a specific worksheet row.
     *
     * @param string $spreadsheetId
     * @param string $sheetTitle
     * @param array  $fieldMapData
     * @param array  $rowsData
     */
    public function updateWorksheetRow($spreadsheetId, $sheetTitle, $fieldMapData, $rowsData): array
    {
        $targetRange = $fieldMapData['targetRange'] ?? '';
        $values = $this->fillMissingColumns($rowsData, $targetRange);
        $payload = JSON::encode(['values' => $values]);

        $url = GoogleSheetCommons::BASE_URL . "/spreadsheets/{$spreadsheetId}/values/" . urlencode("{$sheetTitle}!{$targetRange}") . '?valueInputOption=USER_ENTERED';
        $response = $this->commons->getHttp()->request($url, 'PUT', $payload);

        return [
            'response'    => $response,
            'payload'     => $payload,
            'status_code' => $this->commons->getHttp()->getResponseCode()
        ];
    }

    /**
     * Delete a row by its index.
     *
     * @param string $spreadsheetId
     * @param string $sheetTitle
     * @param mixed  $rowId
     */
    public function deleteRow($spreadsheetId, $sheetTitle, $rowId): array
    {
        $rowId = (int) $rowId;
        $range = "{$sheetTitle}!A{$rowId}:ZZZ{$rowId}";
        $url = GoogleSheetCommons::BASE_URL . "/spreadsheets/{$spreadsheetId}/values/" . urlencode($range) . ':clear';
        $response = $this->commons->getHttp()->request($url, 'POST', []);

        return [
            'response'    => $response,
            'payload'     => compact('range'),
            'status_code' => $this->commons->getHttp()->getResponseCode()
        ];
    }

    /**
     * Get a single row by its number.
     *
     * @param string $spreadsheetId
     * @param string $sheetTitle
     * @param mixed  $rowNumber
     */
    public function getRowByNumber($spreadsheetId, $sheetTitle, $rowNumber): array
    {
        $rowNumber = (int) $rowNumber;
        $url = GoogleSheetCommons::BASE_URL . '/spreadsheets/' . $spreadsheetId . '/values/' . urlencode("{$sheetTitle}!A{$rowNumber}:ZZZ{$rowNumber}");
        $response = $this->commons->getHttp()->request($url, 'GET', []);

        return [
            'response'    => $response->values[0] ?? [],
            'payload'     => compact('rowNumber'),
            'status_code' => $this->commons->getHttp()->getResponseCode()
        ];
    }

    /**
     * Get all rows of a worksheet.
     *
     * @param string $spreadsheetId
     * @param string $sheetTitle
     */
    public function getRows($spreadsheetId, $sheetTitle): array
    {
        $fetchedData = $this->commons->fetchSheetData($spreadsheetId, $sheetTitle);

        return [
            'response'    => $fetchedData->values ?? [],
            'payload'     => $spreadsheetId,
            'status_code' => $this->commons->getHttp()->getResponseCode()
        ];
    }

    /**
     * Get worksheet rows for OnRowAdded polling.
     */
    public function getRow(array $configs): array
    {
        $spreadsheetId = $configs['spreadsheet-id']['value'] ?? '';
        $sheetTitle = $configs['sheet-title']['value'] ?? '';
        $fetchedData = $this->commons->fetchSheetData($spreadsheetId, $sheetTitle);

        $values = $fetchedData->values ?? [];
        if (empty($values)) {
            return [
                'status'      => 'success',
                'response'    => [],
                'payload'     => '',
                'status_code' => 200
            ];
        }

        $headers = array_shift($values);
        $rows = [];
        $rowCount = 1; // Header was row 1

        foreach ($values as $rowData) {
            ++$rowCount;
            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = $rowData[$index] ?? '';
            }
            $row['_row_number'] = $rowCount;
            $rows[] = $row;
        }

        return [
            'status'      => 'success',
            'response'    => $rows,
            'payload'     => '',
            'status_code' => $this->commons->getHttp()->getResponseCode()
        ];
    }

    /**
     * Create a new column in a worksheet.
     *
     * @param string $spreadsheetId
     * @param string $sheetTitle
     * @param string $columnName
     * @param mixed  $columnIndex
     */
    public function createColumn($spreadsheetId, $sheetTitle, $columnName, $columnIndex): array
    {
        $sheetId = $this->commons->getSheetIdByTitle($spreadsheetId, $sheetTitle);
        $columnIndex = (int) ($columnIndex ?? 0);

        if ($columnIndex < 1) {
            $fetched = $this->commons->fetchSheetData($spreadsheetId, $sheetTitle);
            $totalCols = 0;

            if (isset($fetched->values[0])) {
                $totalCols = \count($fetched->values[0]);
            }

            $columnIndex = $totalCols + 1;
        }

        $url = GoogleSheetCommons::BASE_URL . "/spreadsheets/{$spreadsheetId}:batchUpdate";
        $payload = [
            'requests' => [
                [
                    'insertDimension' => [
                        'range' => [
                            'sheetId'    => $sheetId,
                            'dimension'  => 'COLUMNS',
                            'startIndex' => $columnIndex - 1,
                            'endIndex'   => $columnIndex
                        ]
                    ]
                ],
            ]
        ];

        $this->commons->getHttp()->request($url, 'POST', JSON::encode($payload));
        $columnLabel = $this->commons->columnIndexToLetter($columnIndex - 1);
        $headerRange = "{$sheetTitle}!{$columnLabel}1";
        $headerUrl = GoogleSheetCommons::BASE_URL . "/spreadsheets/{$spreadsheetId}/values/" . urlencode($headerRange) . '?valueInputOption=USER_ENTERED';
        $response = $this->commons->getHttp()->request($headerUrl, 'PUT', JSON::encode(['values' => [[$columnName]]]));

        return [
            'response'    => $response,
            'payload'     => compact('columnName', 'columnIndex'),
            'status_code' => $this->commons->getHttp()->getResponseCode()
        ];
    }

    /**
     * Prepare data for batch updating multiple cells.
     */
    private function prepareDataForUpdate(string $sheetTitle, int $rowId, array $oldData, array $newData): array
    {
        $updatePayload = [];
        $rowLength = max(\count($oldData), max(array_keys($newData)) + 1);
        for ($col = 0; $col < $rowLength; ++$col) {
            $oldVal = $oldData[$col] ?? '';
            $newVal = $newData[$col] ?? $oldVal;
            if ($oldVal !== $newVal) {
                $updatePayload[] = ['range' => $sheetTitle . '!' . $this->commons->columnIndexToLetter($col) . ($rowId + 1), 'values' => [[$newVal]]];
            }
        }

        return ['data' => $updatePayload, 'valueInputOption' => 'USER_ENTERED'];
    }

    /**
     * Get the mapped index of a column from trigger config.
     */
    private function getColumnToMatchId(array $configs): int
    {
        $val = $configs['column-to-match-on']['value'] ?? '';
        $part = explode(':', $val)[0];

        return ctype_alpha($part) ? $this->commons->excelColumnToIndex($part) : (int) $part;
    }

    /**
     * Align row data with target range, filling missing columns with null.
     *
     * @param array  $rowsData
     * @param string $range
     */
    private function fillMissingColumns($rowsData, $range): array
    {
        $range = explode('!', $range);
        $range = end($range);
        $parts = explode(':', $range);
        $startCol = preg_replace('/[0-9]/', '', $parts[0]);
        $endCol = preg_replace('/[0-9]/', '', $parts[1] ?? $parts[0]);

        $result = [];
        $startIndex = $this->commons->excelColumnToIndex($startCol);
        $endIndex = $this->commons->excelColumnToIndex($endCol);

        for ($i = $startIndex; $i <= $endIndex; ++$i) {
            $result[] = $rowsData[$i] ?? null;
        }

        return [$result];
    }

    /**
     * Find matched row index for append-or-update.
     */
    private function getRowToUpdate(array $data, int $columnToMatch, string $valueToMatch): array
    {
        foreach ($data as $rowId => $rowValues) {
            if (isset($rowValues[$columnToMatch]) && (string) $rowValues[$columnToMatch] === (string) $valueToMatch) {
                return ['matchedRow' => $rowId, 'values' => $rowValues];
            }
        }

        return ['matchedRow' => -1, 'values' => []];
    }
}
