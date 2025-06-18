<?php

namespace BitApps\Pi\src\Interfaces;

if (!\defined('ABSPATH')) {
    exit;
}


interface ActionInterface
{
    /**
     * Executes the action.
     *
     * @return array{
     *     status: 'success'|'error',
     *     input: array,
     *     output: array,
     *     message?: string
     * } Array containing 'output', 'input', and 'status' keys, with an optional 'message' key
     */
    public function execute(): array;
}
