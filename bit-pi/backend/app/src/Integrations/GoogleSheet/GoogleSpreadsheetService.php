<?php

namespace BitApps\Pi\src\Integrations\GoogleSheet;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\src\Integrations\GoogleSheet\Helpers\GoogleSheetCommons;

/**
 * Service class for spreadsheet-level operations.
 */
final class GoogleSpreadsheetService
{
    private GoogleSheetCommons $commons;

    public function __construct(GoogleSheetCommons $commons)
    {
        $this->commons = $commons;
    }

    /**
     * Get all spreadsheets of the user.
     */
    public function getSpreadsheets(): array
    {
        $url = GoogleSheetCommons::DRIVE_URL . "/files?q=mimeType%3D'application/vnd.google-apps.spreadsheet'+and+trashed%3Dfalse&fields=files(*)&orderBy=createdTime+desc";
        $response = $this->commons->getHttp()->request($url, 'GET', []);

        return [
            'status'   => 'success',
            'response' => $response->files ?? [],
            'payload'  => []
        ];
    }

    /**
     * Create a new spreadsheet.
     */
    public function createSpreadsheet(array $fieldMapData): array
    {
        $url = GoogleSheetCommons::BASE_URL . '/spreadsheets';
        $response = $this->commons->getHttp()->request($url, 'POST', JSON::encode($fieldMapData));

        return [
            'response'    => $response,
            'payload'     => $fieldMapData,
            'status_code' => $this->commons->getHttp()->getResponseCode()
        ];
    }

    /**
     * Find spreadsheets by name.
     */
    public function findSpreadsheets(string $title, int $limit): array
    {
        $url = GoogleSheetCommons::DRIVE_URL . "/files?q=name contains '{$title}' and mimeType='application/vnd.google-apps.spreadsheet'&pageSize={$limit}";
        $response = $this->commons->getHttp()->request($url, 'GET', []);

        return [
            'response'    => $response,
            'payload'     => compact('title', 'limit'),
            'status_code' => $this->commons->getHttp()->getResponseCode()
        ];
    }

    /**
     * Delete a spreadsheet file.
     *
     * @param string $spreadsheetId
     */
    public function deleteSpreadsheet($spreadsheetId): array
    {
        $url = GoogleSheetCommons::DRIVE_URL . "/files/{$spreadsheetId}";
        $response = $this->commons->getHttp()->request($url, 'DELETE', []);

        return [
            'response'    => $response,
            'payload'     => compact('spreadsheetId'),
            'status_code' => $this->commons->getHttp()->getResponseCode()
        ];
    }
}
