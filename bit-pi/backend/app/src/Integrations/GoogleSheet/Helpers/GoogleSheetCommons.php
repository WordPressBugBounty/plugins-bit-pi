<?php

namespace BitApps\Pi\src\Integrations\GoogleSheet\Helpers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Common helper utilities shared across Google Sheet service classes.
 */
final class GoogleSheetCommons
{
    public const BASE_URL = 'https://sheets.googleapis.com/v4';

    public const DRIVE_URL = 'https://www.googleapis.com/drive/v3';

    private $http;

    /**
     * GoogleSheetCommons constructor.
     *
     * @param mixed $httpClient
     */
    public function __construct($httpClient)
    {
        $this->http = $httpClient;
    }

    /**
     * Get the HTTP client instance.
     *
     * @return mixed
     */
    public function getHttp()
    {
        return $this->http;
    }

    /**
     * Fetch all values from a worksheet.
     *
     * @return object
     */
    public function fetchSheetData(string $spreadsheetId, string $sheetTitle)
    {
        $url = self::BASE_URL . '/spreadsheets/' . $spreadsheetId . '/values/' . urlencode($sheetTitle);

        return $this->http->request($url, 'GET', []);
    }

    /**
     * Get sheetId (numeric) by its title.
     */
    public function getSheetIdByTitle(string $spreadsheetId, string $sheetTitle): ?int
    {
        $properties = $this->getSheetPropertiesByTitle($spreadsheetId, $sheetTitle);

        return $properties->sheetId ?? null;
    }

    /**
     * Fetch sheet properties by matching title.
     *
     * @return null|object
     */
    public function getSheetPropertiesByTitle(string $spreadsheetId, string $sheetTitle)
    {
        $url = self::BASE_URL . "/spreadsheets/{$spreadsheetId}?fields=sheets.properties";
        $response = $this->http->request($url, 'GET', []);
        foreach ($response->sheets ?? [] as $sheet) {
            if (($sheet->properties->title ?? '') === $sheetTitle) {
                return $sheet->properties;
            }
        }
    }

    /**
     * Convert Excel-style column letter to 0-based index.
     */
    public function excelColumnToIndex(string $column): int
    {
        $column = strtoupper($column);
        $index = 0;
        for ($i = 0; $i < \strlen($column); ++$i) {
            $index *= 26;
            $index += \ord($column[$i]) - \ord('A') + 1;
        }

        return $index - 1;
    }

    /**
     * Convert 0-based index to Excel-style column letter.
     */
    public function columnIndexToLetter(int $columnIndex): string
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
