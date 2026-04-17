<?php

namespace BitApps\Pi\src\Tools\AiAgent\Memory;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for AI Agent memory implementations.
 *
 * All memory types must implement this interface to ensure consistency.
 */
interface MemoryInterface
{
    /**
     * Store messages in memory.
     *
     * @param array $messages Array of message objects
     *
     * @return bool True on success, false on failure
     */
    public function store(array $messages): bool;

    /**
     * Retrieve messages from memory.
     *
     * @return array Array of stored messages, or empty array if none found
     */
    public function retrieve(): array;

    /**
     * Clear memory.
     *
     * @return bool True on success, false on failure
     */
    public function clear(): bool;

    /**
     * Check if memory exists.
     *
     * @return bool True if memory exists, false otherwise
     */
    public function exists(): bool;

    /**
     * Add a new message to existing memory.
     *
     * @param array $message The message to add
     *
     * @return bool True on success, false on failure
     */
    public function addMessage(array $message): bool;

    /**
     * Get the number of messages in memory.
     *
     * @return int Number of messages
     */
    public function count(): int;

    /**
     * Get memory statistics.
     *
     * @return array Memory statistics
     */
    public function getStats(): array;
}
