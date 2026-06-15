<?php

namespace BitApps\Pi\src\Integrations\GoogleSheet;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\src\Integrations\GoogleSheet\Helpers\GoogleSheetCommons;

/**
 * Service class for sheet/tab-level operations.
 */
final class GoogleSheetService
{
    private GoogleSheetCommons $commons;

    public function __construct(GoogleSheetCommons $commons)
    {
        $this->commons = $commons;
    }

    /**
     * Get all worksheets of a spreadsheet.
     */
    public function getWorksheets(string $spreadsheetId): array
    {
        $url = GoogleSheetCommons::BASE_URL . "/spreadsheets/{$spreadsheetId}?fields=sheets.properties";
        $response = $this->commons->getHttp()->request($url, 'GET', []);

        $worksheets = [];
        foreach ($response->sheets ?? [] as $sheet) {
            $worksheets[] = $sheet->properties ?? [];
        }

        return [
            'status'   => 'success',
            'response' => $worksheets,
            'payload'  => compact('spreadsheetId')
        ];
    }

    /**
     * Create a new worksheet in a spreadsheet.
     *
     * @param string $spreadsheetId
     * @param array  $fieldMapData
     */
    public function createSheet($spreadsheetId, $fieldMapData): array
    {
        $url = GoogleSheetCommons::BASE_URL . '/spreadsheets/' . $spreadsheetId . ':batchUpdate';
        $payload = ['requests' => [$fieldMapData]];
        $response = $this->commons->getHttp()->request($url, 'POST', JSON::encode($payload));

        return [
            'response'    => $response,
            'payload'     => $payload,
            'status_code' => $this->commons->getHttp()->getResponseCode()
        ];
    }

    /**
     * Find a worksheet by its title.
     *
     * @param string $spreadsheetId
     */
    public function findWorksheet($spreadsheetId, string $title, bool $exactMatch): array
    {
        $url = GoogleSheetCommons::BASE_URL . "/spreadsheets/{$spreadsheetId}?fields=sheets.properties";
        $response = $this->commons->getHttp()->request($url, 'GET', []);

        $matchedSheets = [];
        if (isset($response->sheets)) {
            foreach ($response->sheets as $sheet) {
                $sheetTitle = $sheet->properties->title ?? '';
                if ($exactMatch ? ($sheetTitle === $title) : (stripos($sheetTitle, $title) !== false)) {
                    $matchedSheets[] = $sheet->properties;
                }
            }
        }

        return [
            'response'    => ['found' => \count($matchedSheets) > 0, 'worksheets' => $matchedSheets],
            'payload'     => compact('spreadsheetId', 'title', 'exactMatch'),
            'status_code' => $this->commons->getHttp()->getResponseCode()
        ];
    }

    /**
     * Copy a worksheet to another document.
     *
     * @param string $spreadsheetId
     * @param string $sheetTitle
     * @param array  $fieldMapData
     */
    public function copySheet($spreadsheetId, $sheetTitle, $fieldMapData): array
    {
        $workSheetId = $this->commons->getSheetIdByTitle($spreadsheetId, $sheetTitle);
        $url = GoogleSheetCommons::BASE_URL . "/spreadsheets/{$spreadsheetId}/sheets/{$workSheetId}:copyTo";
        $response = $this->commons->getHttp()->request($url, 'POST', JSON::encode($fieldMapData));

        return [
            'response'    => $response,
            'payload'     => $fieldMapData,
            'status_code' => $this->commons->getHttp()->getResponseCode()
        ];
    }

    /**
     * Delete a worksheet from a spreadsheet.
     *
     * @param string $spreadsheetId
     * @param string $sheetTitle
     */
    public function deleteSheet($spreadsheetId, $sheetTitle): array
    {
        $sheetId = $this->commons->getSheetIdByTitle($spreadsheetId, $sheetTitle);

        $url = GoogleSheetCommons::BASE_URL . "/spreadsheets/{$spreadsheetId}:batchUpdate";
        $payload = [
            'requests' => [
                [
                    'deleteSheet' => [
                        'sheetId' => $sheetId
                    ]
                ],
            ]
        ];

        $response = $this->commons->getHttp()->request($url, 'POST', JSON::encode($payload));

        return [
            'response'    => $response,
            'payload'     => compact('sheetTitle'),
            'status_code' => $this->commons->getHttp()->getResponseCode()
        ];
    }

    /**
     * Clear a worksheet or specific range.
     *
     * @param string $spreadsheetId
     * @param string $sheetTitle
     * @param bool   $isFirstRowHeaders
     */
    public function clearSheet($spreadsheetId, $sheetTitle, $isFirstRowHeaders): array
    {
        $startIndex = $isFirstRowHeaders ? 2 : 1;
        $range = "{$sheetTitle}!A{$startIndex}:ZZZ";
        $url = GoogleSheetCommons::BASE_URL . "/spreadsheets/{$spreadsheetId}/values/" . urlencode($range) . ':clear';
        $response = $this->commons->getHttp()->request($url, 'POST', []);

        return [
            'response'    => $response,
            'payload'     => compact('range', 'isFirstRowHeaders'),
            'status_code' => $this->commons->getHttp()->getResponseCode()
        ];
    }

    /**
     * Export a worksheet in a specific format.
     *
     * @param string $spreadsheetId
     * @param string $sheetTitle
     * @param string $format
     */
    public function exportSheet($spreadsheetId, $sheetTitle, $format): array
    {
        $sheetId = $this->commons->getSheetIdByTitle($spreadsheetId, $sheetTitle);
        $exportUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/export?format={$format}&id={$spreadsheetId}&gid={$sheetId}";

        return [
            'response'    => ['file_url' => $exportUrl],
            'payload'     => compact('spreadsheetId', 'sheetTitle', 'format'),
            'status_code' => $this->commons->getHttp()->getResponseCode()
        ];
    }
}
