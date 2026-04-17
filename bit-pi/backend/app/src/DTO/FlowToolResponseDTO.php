<?php

namespace BitApps\Pi\src\DTO;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FlowToolResponseDTO.
 *
 * A Data Transfer Object for standardizing tool responses within workflow processes.
 * This DTO encapsulates the response structure for various workflow tools, ensuring
 * consistent data format when returning results and controlling flow execution.
 */
class FlowToolResponseDTO
{
    /**
     * Whether the tool operation was successful.
     *
     * @var bool
     */
    private $status;

    /**
     * Human-readable message about the tool operation result.
     *
     * @var string
     */
    private $message;

    /**
     * Input data that was provided to the tool.
     *
     * @var array
     */
    private $input;

    /**
     * Output data resulting from the tool operation.
     *
     * @var array
     */
    private $output;

    /**
     * Additional details about the tool operation.
     *
     * @var array
     */
    private $details;

    /**
     * Flag indicating whether the next node in a workflow should execute.
     *
     * @var bool
     */
    private $isNextNodeBlocked;

    /**
     * Node ID.
     *
     * @var string
     */
    private $nodeId;

    private $shouldSaveToLog = false;

    /**
     * FlowToolResponseDTO constructor.
     *
     * @param string   $status          Whether the tool operation was success or error
     * @param string $message           Human-readable message about the operation result
     * @param array  $input             Input data provided to the tool
     * @param array  $output            Output data resulting from the tool operation
     * @param array  $details           Additional details about the operation
     * @param bool   $isNextNodeBlocked Flag to indicate if the next node should be blocked
     * @param string $nodeId          Node ID
     */
    public function __construct(
        string $status,
        array $input,
        array $output,
        string $message,
        array $details = [],
        bool $isNextNodeBlocked = false,
        ?string $nodeId = null,
        bool $shouldSaveToLog = true
    ) {
        $this->status = $status;
        $this->message = $message;
        $this->input = $input;
        $this->output = $output;
        $this->details = $details;
        $this->isNextNodeBlocked = $isNextNodeBlocked;
        $this->nodeId = $nodeId;
        $this->shouldSaveToLog = $shouldSaveToLog;
    }

    /**
     * Create a response.
     *
     * @param string      $status              Status of the operation
     * @param array       $input               Input data provided to the tool
     * @param array       $output              Output data from the tool
     * @param null|string $message             Success/error message
     * @param array       $details             Additional operation details
     * @param null|string $nodeId              Optional node identifier
     * @param bool        $isNextNodeBlocked   Flag to indicate if the next node should be blocked
     * @param bool        $shouldSaveToLog Flag to indicate if the response should be saved to history
     *
     * @return array      Response as an array
     */
    public static function create(
        string $status,
        array $input = [],
        array $output = [],
        ?string $message = null,
        array $details = [],
        bool $isNextNodeBlocked = false,
        ?string $nodeId = null,
        bool $shouldSaveToLog = true
    ): array {
        return (new self(
            $status,
            $input,
            $output,
            $message,
            $details,
            $isNextNodeBlocked,
            $nodeId,
            $shouldSaveToLog
        ))->toArray();
    }

    public function toArray(): array
    {
        return [
            'status'            => $this->status,
            'input'             => $this->input,
            'output'            => $this->output,
            'message'           => $this->message,
            'details'           => $this->details,
            'isNextNodeBlocked' => $this->isNextNodeBlocked,
            'nodeId'            => $this->nodeId,
            'shouldSaveToLog'   => $this->shouldSaveToLog,
        ];
    }
}
