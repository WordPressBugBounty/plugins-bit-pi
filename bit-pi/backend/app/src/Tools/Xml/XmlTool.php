<?php

namespace BitApps\Pi\src\Tools\Xml;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Helpers\MixInputHandler;
use BitApps\Pi\Model\FlowLog;
use BitApps\Pi\src\DTO\FlowToolResponseDTO;
use BitApps\Pi\src\Flow\GlobalNodeVariables;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Tools\FlowToolsFactory;
use DOMDocument;
use InvalidArgumentException;
use SimpleXMLElement;

class XmlTool
{
    private const MACHINE_SLUG = 'xml';

    protected $nodeInfoProvider;

    private $flowHistoryId;

    public function __construct(NodeInfoProvider $nodeInfoProvider, $flowHistoryId)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
        $this->flowHistoryId = $flowHistoryId;
    }

    public function execute(): array
    {
        $xmlConfig = $this->nodeInfoProvider->getData()['xml'] ?? [];

        $flowId = $this->nodeInfoProvider->getFlowId();

        $nodeId = $this->nodeInfoProvider->getNodeId();

        $xmlMode = $xmlConfig['xml_mode'] ?? 'parse_xml';

        $xmlContent = $xmlConfig['content'] ?? '';

        $xmlQueryExpression = MixInputHandler::replaceMixTagValue($xmlConfig['xpath_query'] ?? '');

        $xmlContent = MixInputHandler::replaceMixTagValue($xmlContent);

        $convertedParsedData = $this->convertContentByType($xmlMode, $xmlContent, $xmlQueryExpression);

        if (isset($convertedParsedData['error'])) {
            $errorMessage = $convertedParsedData['error'];

            return FlowToolResponseDTO::create(
                FlowLog::STATUS['ERROR'],
                [$xmlContent],
                [$errorMessage],
                $errorMessage,
                [
                    'app_slug'     => FlowToolsFactory::APP_SLUG,
                    'machine_slug' => self::MACHINE_SLUG,
                    'xml_mode'     => $xmlMode
                ]
            );
        }

        if (\is_string($convertedParsedData)) {
            $convertedParsedData = [$convertedParsedData];
        }

        if (\is_string($xmlContent)) {
            $xmlContent = [$xmlContent];
        }

        $details = [
            'app_slug'     => FlowToolsFactory::APP_SLUG,
            'machine_slug' => self::MACHINE_SLUG,
            'xml_mode'     => $xmlMode,
        ];

        $successMessage = 'Successfully parsed XML.';

        if ($xmlMode === 'xpath') {
            $successMessage = 'Successfully executed XPath query.';
        }

        $nodeVariableInstance = GlobalNodeVariables::getInstance($this->flowHistoryId, $flowId);

        $nodeVariableInstance->setVariables($nodeId, $convertedParsedData);

        $nodeVariableInstance->setNodeResponse($nodeId, $convertedParsedData);

        return FlowToolResponseDTO::create(
            FlowLog::STATUS['SUCCESS'],
            $xmlContent,
            $convertedParsedData,
            $successMessage,
            $details,
        );
    }

    private function convertContentByType(string $type, string $content, string $xmlQueryExpression = '')
    {
        switch ($type) {
            // TODO: Uncomment the following cases if needed
            // case 'xml_to_json':
            //     $result = $this->validateAndFormatXML($content);

            //     if (isset($result['error'])) {
            //         return [
            //             'error' => $result['error'],
            //         ];
            //     }

            //     $validatedXml = $result['formattedXml'];

            //     return JSON::encode($validatedXml);

            // case 'json_to_xml':
            //     // Step 1: Remove backslashes used for escaping (e.g., \" or \/)
            //     $unescapedContent = stripcslashes($content);

            //     // Step 2: Remove wrapping double quotes (if present)
            //     $rawXmlString = trim($unescapedContent, '"');

            //     $jsonDecoded = JSON::decode($rawXmlString, true);

            //     if (!$jsonDecoded) {
            //         return [
            //             'error' => 'Invalid JSON provided for conversion to XML.',
            //         ];
            //     }

            //     return $this->arrayToXml($jsonDecoded);

            case 'xpath':
                if (empty($xmlQueryExpression)) {
                    return [
                        'error' => 'XPath query expression is required for xpath mode.',
                    ];
                }

                $result = $this->validateAndFormatXML($content);

                if (isset($result['error'])) {
                    return [
                        'error' => $result['error'],
                    ];
                }

                $validatedXml = $result['formattedXml'];

                $xml = new SimpleXMLElement($validatedXml);

                $queryResult = $xml->xpath($xmlQueryExpression);

                if ($queryResult === false) {
                    return [
                        'error' => 'Invalid XPath query expression provided.',
                    ];
                }

                return JSON::decode(JSON::encode($queryResult), true);


            case 'parse_xml':
                $result = $this->validateAndFormatXML($content);

                if (isset($result['error'])) {
                    return [
                        'error' => $result['error'],
                    ];
                }

                $validatedXml = $result['formattedXml'];


                $xmlElement = simplexml_load_string($validatedXml);

                return JSON::decode(JSON::encode($xmlElement), true);

            default:
                throw new InvalidArgumentException(esc_html("Unsupported type: {$type}"));
        }
    }

    private function validateAndFormatXML($rawXml)
    {
        // Clean non-breaking spaces
        $cleanXml = str_replace("\xC2\xA0", ' ', $rawXml);

        $cleanXml = stripslashes(trim($cleanXml, '"'));

        // Enable internal error tracking
        libxml_use_internal_errors(true);

        $dom = new DOMDocument();

        $dom->preserveWhiteSpace = false;

        $dom->formatOutput = true;

        if (!$dom->loadXML($cleanXml)) {
            $errors = libxml_get_errors();

            libxml_clear_errors();

            return [
                'error' => $errors[0]->message
            ];
        }

        return [
            'formattedXml' => $dom->saveXML()
        ];
    }

    private function arrayToXml(array $data, ?SimpleXMLElement $xml = null): string
    {
        if ($xml === null) {
            $xml = new SimpleXMLElement('<root/>');
        }

        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                $this->arrayToXml($value, $xml->addChild(is_numeric($key) ? "item{$key}" : $key));
            } else {
                $xml->addChild(is_numeric($key) ? "item{$key}" : $key, htmlspecialchars((string) $value));
            }
        }

        return $xml->asXML();
    }
}
