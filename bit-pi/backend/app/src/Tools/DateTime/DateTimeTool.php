<?php

namespace BitApps\Pi\src\Tools\DateTime;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\DateTimeHelper;
use BitApps\Pi\Helpers\MixInputHandler;
use BitApps\Pi\Model\FlowLog;
use BitApps\Pi\src\DTO\FlowToolResponseDTO;
use BitApps\Pi\src\Flow\GlobalNodeVariables;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Tools\FlowToolsFactory;
use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;

class DateTimeTool
{
    private const MACHINE_SLUG = 'dateTime';

    private $nodeInfoProvider;

    private $flowHistoryId;

    public function __construct(NodeInfoProvider $nodeInfoProvider, $flowHistoryId)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
        $this->flowHistoryId = $flowHistoryId;
    }

    /**
     * Execute DateTime tool logic.
     */
    public function execute()
    {
        $dateTimeConfig = $this->nodeInfoProvider->getData()['dateTime'];
        $nodeId = $this->nodeInfoProvider->getNodeId();

        $result = $this->executeDateTimeTool($dateTimeConfig);

        $nodeVariableInstance = GlobalNodeVariables::getInstance($this->flowHistoryId, $this->nodeInfoProvider->getFlowId());

        $output = $result['output'] ?? [
            'error' => $result['message']
        ];

        $nodeVariableInstance->setVariables($nodeId, $output);
        $nodeVariableInstance->setNodeResponse($nodeId, $output);

        return FlowToolResponseDTO::create(
            $result['success'] ? FlowLog::STATUS['SUCCESS'] : FlowLog::STATUS['ERROR'],
            $result['input'] ?? [],
            $output,
            $result['message'] ?? 'DateTime tool executed successfully',
            [
                'app_slug'     => FlowToolsFactory::APP_SLUG,
                'machine_slug' => self::MACHINE_SLUG,
            ]
        );
    }

    /**
     * Execute DateTime-specific logic.
     *
     * @param array $config Tool configuration from node state
     *
     * @return array
     */
    private function executeDateTimeTool($config)
    {
        $action = $config['action'] ?? 'format';
        $timezone = DateTimeHelper::wp_timezone();

        switch ($action) {
            case 'format':
                return $this->formatDate($config, $timezone);

            case 'parse':
                return $this->parseDate($config, $timezone);

            case 'add':
            case 'subtract':
                return $this->modifyTime($config, $timezone, $action);

            case 'current':
                return $this->getCurrentDateTime();

            case 'difference':
                return $this->getTimeBetweenDates($config, $timezone);

            default:
                return [
                    'success' => false,
                    'message' => 'Unknown action: ' . $action,
                    'input'   => [],
                    'output'  => [],
                ];
        }
    }

    /**
     * Format a date/time value.
     *
     * @param array  $config
     * @param mixed $timezone
     *
     * @return array
     */
    private function formatDate($config, $timezone)
    {
        $dateValue = MixInputHandler::replaceMixTagValue($config['dateValue']);

        if (empty($dateValue)) {
            return [
                'success' => false,
                'message' => 'Date value is required for formatting',
            ];
        }
        $input = ['date' => $dateValue];

        try {
            $helper = new DateTimeHelper();
            $inputFormat = $this->getInputFormat($config);
            $outputFormat = $this->getOutputFormat($config);

            $formattedDate = $helper->getFormated($dateValue, $inputFormat, $timezone, $outputFormat, $timezone);

            if (!$formattedDate) {
                $formattedDate = $helper->getFormated($dateValue, false, $timezone, $outputFormat, $timezone);
            }

            if (!$formattedDate) {
                return [
                    'success' => false,
                    'message' => 'Failed to format date. The date value does not match the specified input format.',
                    'input'   => $input,
                ];
            }

            return [
                'success' => true,
                'message' => 'Date formatted successfully',
                'output'  => ['formatted-date' => $formattedDate],
                'input'   => $input,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to format date: ' . $e->getMessage(),
                'input'   => $input,
            ];
        }
    }

    /**
     * Parse a date/time string.
     *
     * @param array         $config
     * @param DateTimeZone $timezone
     *
     * @return array
     */
    private function parseDate($config, $timezone)
    {
        $dateValue = MixInputHandler::replaceMixTagValue($config['dateValue']) ?? '';

        if (empty($dateValue)) {
            return [
                'success' => false,
                'message' => 'Date value is required for parsing',
                'input'   => ['date' => '']
            ];
        }

        $input = ['date' => $dateValue];

        try {
            $dateTime = $this->createDateTime($dateValue, $config, $timezone);

            return [
                'success' => true,
                'message' => 'Date parsed successfully',
                'input'   => $input,
                'output'  => [
                    'day'       => $dateTime->format('D'),
                    'month'     => $dateTime->format('M'),
                    'year'      => $dateTime->format('Y'),
                    'hour'      => $dateTime->format('H'),
                    'minute'    => $dateTime->format('i'),
                    'second'    => $dateTime->format('s'),
                    'timestamp' => $dateTime->getTimestamp(),
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to parse date: ' . $e->getMessage(),
                'input'   => $input,
            ];
        }
    }

    /**
     * Add or subtract time from a date.
     *
     * @param array         $config
     * @param DateTimeZone $timezone
     * @param string        $operation 'add' or 'subtract'
     *
     * @return array
     */
    private function modifyTime($config, $timezone, $operation)
    {
        $dateValue = MixInputHandler::replaceMixTagValue($config['dateValue'] ?? 'now');
        $amount = MixInputHandler::replaceMixTagValue($config['duration'] ?? 0);
        $unit = $config['unit'] ?? 'days';
        $input = ['date' => $dateValue, 'duration' => $amount, 'unit' => $unit];

        try {
            $dateTime = $this->createDateTime($dateValue, $config, $timezone);

            $interval = DateInterval::createFromDateString($amount . ' ' . $unit);
            $operation === 'add' ? $dateTime->add($interval) : $dateTime->sub($interval);

            return [
                'success' => true,
                'message' => $operation === 'add' ? 'Time added successfully' : 'Time subtracted successfully',
                'output'  => ['modified-date' => $dateTime->format($this->getOutputFormat($config))],
                'input'   => $input,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => ($operation === 'add' ? 'Failed to add time: ' : 'Failed to subtract time: ') . $e->getMessage(),
                'input'   => $input
            ];
        }
    }

    /**
     * Get current date/time.
     *
     * @return array
     */
    private function getCurrentDateTime()
    {
        $dateTimeHelper = new DateTimeHelper();

        return [
            'success' => true,
            'message' => 'Current date/time retrieved successfully',
            'output'  => [
                'date-time' => $dateTimeHelper->getDate() . ' ' . $dateTimeHelper->getTime(),
                'timestamp' => (int) time(),
            ],
            'input' => [],
        ];
    }

    /**
     * Get time difference between two dates.
     *
     * @param array         $config
     * @param DateTimeZone $timezone
     *
     * @return array
     */
    private function getTimeBetweenDates($config, $timezone)
    {
        $startDate = MixInputHandler::replaceMixTagValue($config['startDate']) ?? '';
        $endDate = MixInputHandler::replaceMixTagValue($config['endDate']) ?? '';
        $units = $config['units'] ?? [];
        $input = ['startDate' => $startDate, 'endDate' => $endDate, 'units' => $units];

        if (empty($startDate) || empty($endDate)) {
            return [
                'success' => false,
                'message' => 'Start date and end date are required',
                'input'   => $input,
            ];
        }

        if (empty($units) || !\is_array($units)) {
            return [
                'success' => false,
                'message' => 'At least one unit must be selected',
                'input'   => $input,
            ];
        }

        try {
            $startDateTime = new DateTime($startDate, $timezone);
            $endDateTime = new DateTime($endDate, $timezone);

            $diff = $startDateTime->diff($endDateTime);

            $result = [];
            $totalSeconds = $endDateTime->getTimestamp() - $startDateTime->getTimestamp();

            // Calculate values for each requested unit
            foreach ($units as $unit) {
                switch ($unit) {
                    case 'year':
                        $result['year'] = $diff->y;

                        break;

                    case 'month':
                        $totalMonths = ($diff->y * 12) + $diff->m;
                        $result['month'] = $totalMonths;

                        break;

                    case 'week':
                        $result['week'] = floor($totalSeconds / (7 * 24 * 60 * 60));

                        break;

                    case 'day':
                        $result['day'] = $diff->days;

                        break;

                    case 'hour':
                        $result['hour'] = floor($totalSeconds / 3600);

                        break;

                    case 'minute':
                        $result['minute'] = floor($totalSeconds / 60);

                        break;

                    case 'second':
                        $result['second'] = $totalSeconds;

                        break;
                }
            }

            return [
                'success' => true,
                'message' => 'Time difference calculated successfully',
                'input'   => $input,
                'output'  => $result
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to calculate time difference: ' . $e->getMessage(),
                'input'   => $input,
            ];
        }
    }

    /**
     * Create a DateTime object from a date value, using formatType/custom if provided.
     *
     * @param string        $dateValue the date string to parse
     * @param array         $config    tool configuration containing optional formatType and custom keys
     * @param DateTimeZone $timezone  WordPress timezone object
     *
     * @return DateTime
     */
    private function createDateTime($dateValue, $config, $timezone)
    {
        $format = $this->getInputFormat($config);

        if (!empty($format)) {
            $dateTime = DateTime::createFromFormat($format, $dateValue, $timezone);

            if ($dateTime !== false) {
                return $dateTime;
            }
        }

        return new DateTime($dateValue, $timezone);
    }

    /**
     * Get the input date format from config.
     *
     * @param array $config tool configuration containing optional formatType and custom keys
     *
     * @return string
     */
    private function getInputFormat($config)
    {
        $formatType = $config['formatType'] ?? '';
        $customFormat = MixInputHandler::replaceMixTagValue($config['custom'] ?? []);

        if ($formatType === 'custom') {
            return $customFormat ?: 'Y-m-d H:i:s';
        }

        return !empty($formatType) ? $formatType : 'Y-m-d H:i:s';
    }

    /**
     * Get the output date format from config.
     *
     * @param array $config tool configuration containing optional outputFormatType and outputCustom keys
     *
     * @return string
     */
    private function getOutputFormat($config)
    {
        $outputFormatType = $config['outputFormatType'] ?? '';
        $outputCustom = MixInputHandler::replaceMixTagValue($config['outputCustom'] ?? []);

        if ($outputFormatType === 'custom') {
            return $outputCustom ?: 'Y-m-d H:i:s';
        }

        return !empty($outputFormatType) ? $outputFormatType : 'Y-m-d H:i:s';
    }
}
