<?php

namespace BitApps\Pi\src\Tools\Csv;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\Helpers\MixInputHandler;
use BitApps\Pi\Model\FlowLog;
use BitApps\Pi\src\DTO\FlowToolResponseDTO;
use BitApps\Pi\src\Flow\GlobalNodeVariables;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Tools\FlowToolsFactory;

class CsvTool
{
    private const MACHINE_SLUG = 'csv';

    protected $nodeInfoProvider;

    private $flowHistoryId;

    public function __construct(NodeInfoProvider $nodeInfoProvider, $flowHistory)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
        $this->flowHistoryId = $flowHistory;
    }

    public function execute(): array
    {
        $csvConfig = $this->nodeInfoProvider->getData()['csv'] ?? [];

        $nodeId = $this->nodeInfoProvider->getNodeId();

        $csvInputType = $csvConfig['csv_source'] ?? 'raw';

        $appDetails = [
            'app_slug'     => FlowToolsFactory::APP_SLUG,
            'machine_slug' => self::MACHINE_SLUG,
            'csv_source'   => $csvInputType,
        ];

        // Get and validate CSV content based on csv source
        $csvValidated = $this->validateAndGetCsvContent($csvConfig);

        if (isset($csvValidated['error'])) {
            return FlowToolResponseDTO::create(
                FlowLog::STATUS['ERROR'],
                [$csvConfig['content'] ?? $csvConfig['file_path'] ?? ''],
                [$csvValidated['error']],
                $csvValidated['error'],
                $appDetails
            );
        }

        $csvContent = $csvValidated['content'];

        $parsedCsv = $this->parseCsvContent($csvContent, $csvConfig);

        $nodeVariableInstance = GlobalNodeVariables::getInstance($this->flowHistoryId, $this->nodeInfoProvider->getFlowId());

        $nodeVariableInstance->setNodeResponse($nodeId, $parsedCsv);

        $nodeVariableInstance->setVariables($nodeId, $parsedCsv);

        if (isset($parsedCsv['error'])) {
            return FlowToolResponseDTO::create(
                FlowLog::STATUS['ERROR'],
                [$csvContent],
                $parsedCsv,
                $parsedCsv['error'],
                $appDetails
            );
        }


        return FlowToolResponseDTO::create(
            FlowLog::STATUS['SUCCESS'],
            [$csvContent],
            $parsedCsv,
            'CSV executed successfully',
            $appDetails,
        );
    }

    /**
     * Validate and get CSV content based on input type.
     *
     * @param mixed $config
     */
    private function validateAndGetCsvContent($config)
    {
        // TODO:: Add support for raw CSV content input
        // $inputType = $config['csv_source'] ?? 'raw';
        // if ($inputType === 'raw') {
        //     $content = MixInputHandler::replaceMixTagValue($content);

        //     if (empty(trim($content))) {
        //         return ['error' => 'CSV content is empty.'];
        //     }

        //     return ['content' => $content];
        // }

        $filePath = MixInputHandler::replaceMixTagValue($config['file_path'] ?? '');

        if (empty(trim($filePath))) {
            return ['error' => 'File path or URL is required.'];
        }

        return $this->getContentFromSource($filePath);
    }

    /**
     * Get content from file path or URL.
     *
     * @param mixed $source
     */
    private function getContentFromSource($source)
    {
        // Check if it's a URL
        if (filter_var($source, FILTER_VALIDATE_URL)) {
            return $this->getContentFromUrl($source);
        }

        // Handle as file path
        return $this->getContentFromFile($source);
    }

    /**
     * Get content from URL with validation.
     *
     * @param mixed $url
     */
    private function getContentFromUrl($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['error' => 'Invalid URL format: ' . $url];
        }

        $parsedUrl = wp_parse_url($url);

        if (!isset($parsedUrl['scheme']) || !\in_array($parsedUrl['scheme'], ['http', 'https'])) {
            return ['error' => 'URL must use HTTP or HTTPS protocol.'];
        }

        $httpClient = new HttpClient();

        $response = $httpClient->get($url, ['sslverify' => true]);

        if (is_wp_error($response)) {
            return ['error' => 'Failed to fetch URL: ' . $response->get_error_message()];
        }

        $httpCode = $httpClient->getResponseCode();

        if ($httpCode !== 200) {
            return ['error' => 'HTTP Error ' . $httpCode . ' when fetching: ' . $url];
        }


        return ['content' => $response];
    }

    /**
     * Get content from file path with validation.
     *
     * @param mixed $filePath
     */
    private function getContentFromFile($filePath)
    {
        $uploadDir = wp_upload_dir();

        if (empty($uploadDir['basedir'])) {
            return ['error' => 'Unable to determine uploads directory.'];
        }

        if (str_starts_with($filePath, $uploadDir['baseurl'])) {
            $file = $uploadDir['basedir'] . substr($filePath, \strlen($uploadDir['baseurl']));
        } elseif (str_starts_with($filePath, $uploadDir['basedir'])) {
            $file = $filePath;
        } else {
            return ['error' => 'File path must be within the WordPress uploads directory.'];
        }

        $realFile = realpath($file);
        $realUploads = realpath($uploadDir['basedir']);

        if ($realFile === false || $realUploads === false) {
            return ['error' => 'File not found or not readable.'];
        }

        if (!str_starts_with($realFile, $realUploads . \DIRECTORY_SEPARATOR)) {
            return ['error' => 'File path must be within the WordPress uploads directory.'];
        }

        $content = file_get_contents($realFile);

        if ($content === false) {
            return ['error' => 'Failed to read file.'];
        }

        return ['content' => $content];
    }

    /**
     * Parse CSV content with validation.
     *
     * @param mixed $content
     * @param mixed $config
     */
    private function parseCsvContent($content, $config)
    {
        // Basic CSV validation
        $lines = explode("\n", trim($content));

        if (\count($lines) < 1) {
            return ['error' => 'CSV content is empty or invalid.'];
        }

        // Get delimiter
        $delimiter = $this->getDelimiter($config);

        // Parse CSV
        $csvData = [];

        $headers = null;

        $containsHeaders = $config['contains_headers'] ?? false;

        // Use custom headers if provided, or generate column numbers
        $customHeaders = MixInputHandler::replaceMixTagValue($config['csv_header']) ?? '';

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);
            if (empty($line)) {
                continue; // Skip empty lines
            }

            $rowData = str_getcsv($line, $delimiter, '"', '\\');

            if ($lineNumber === 0) {
                if (!$containsHeaders) {
                    $headers = array_map(
                        function ($i) {
                            return 'column_' . ($i + 1);
                        },
                        array_keys($rowData)
                    );

                    continue; // Skip header row if not contains headers
                }

                if (!empty($customHeaders)) {
                    $headers = explode($delimiter, $customHeaders);
                } else {
                    $headers = $rowData;
                }
            }

            // Validate row has same number of columns as headers
            if (\count($rowData) !== \count($headers)) {
                return ['error' => 'Row ' . ($lineNumber + 1) . ' has ' . \count($rowData) . ' columns, expected ' . \count($headers) . ' columns.'];
            }

            $csvData[] = array_combine($headers, $rowData);
        }

        if (empty($csvData)) {
            return ['error' => 'No valid data rows found in CSV.'];
        }

        return ['data' => $csvData, 'headers' => $headers];
    }

    /**
     * Get delimiter character based on config.
     *
     * @param mixed $config
     */
    private function getDelimiter($config)
    {
        $delimiter = $config['delimiter'] ?? 'comma';

        switch ($delimiter) {
            case 'tab':
                return "\t";

            case 'other':
                return $config['delimiter_character'] ?? ',';

            default:
                return ',';
        }
    }
}
