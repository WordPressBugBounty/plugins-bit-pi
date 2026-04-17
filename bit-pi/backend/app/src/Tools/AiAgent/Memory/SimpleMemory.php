<?php

namespace BitApps\Pi\src\Tools\AiAgent\Memory;

use BitApps\Pi\Config;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple memory implementation for AI Agent conversations.
 *
 * Stores conversation history using WordPress transients for simplicity.
 * Each flow history has its own memory storage.
 */
class SimpleMemory implements MemoryInterface
{
    /**
     * Maximum number of messages to keep in memory.
     * Prevents token limit issues with very long conversations.
     */
    private const MAX_MESSAGES = 5;

    /**
     * Transient expiration time (24 hours).
     */
    private const EXPIRATION = 86400;

    /**
     * Node ID for this memory instance.
     *
     * @var string
     */
    private $nodeId;

    /**
     * Maximum messages to store (can be overridden via config).
     *
     * @var int
     */
    private $maxMessages;

    private $userDefinedKey = '';

    /**
     * Constructor.
     *
     * @param string $nodeId The node ID (used as memory key)
     * @param array  $config Optional configuration
     */
    public function __construct($nodeId, array $config = [])
    {
        $this->nodeId = $nodeId;
        $this->maxMessages = self::MAX_MESSAGES;

        if (isset($config['contextLength']) && is_numeric($config['contextLength'])) {
            $this->maxMessages = (int) $config['contextLength'];
        }

        $this->userDefinedKey = $config['userDefinedKey'] ?? '';
    }

    /**
     * Store messages in memory.
     *
     * @param array $messages Array of message objects
     *
     * @return bool True on success, false on failure
     */
    public function store(array $messages): bool
    {
        // Keep only the most recent messages to prevent memory overflow
        if (\count($messages) > $this->maxMessages) {
            // Keep system message if present, then take most recent messages
            $systemMessage = null;
            $otherMessages = [];

            foreach ($messages as $message) {
                if (($message['role'] ?? '') === 'system') {
                    $systemMessage = $message;
                } else {
                    $otherMessages[] = $message;
                }
            }

            // Keep only recent messages
            $otherMessages = \array_slice($otherMessages, -1 * ($this->maxMessages - ($systemMessage ? 1 : 0)));

            // Rebuild messages array
            $messages = $systemMessage ? array_merge([$systemMessage], $otherMessages) : $otherMessages;
        }

        return set_transient($this->getMemoryKey(), $messages, self::EXPIRATION);
    }

    /**
     * Retrieve messages from memory.
     *
     * @return array Array of stored messages, or empty array if none found
     */
    public function retrieve(): array
    {
        $messages = get_transient($this->getMemoryKey());

        return \is_array($messages) ? $messages : [];
    }

    /**
     * Clear memory for this flow history.
     *
     * @return bool True on success, false on failure
     */
    public function clear(): bool
    {
        return delete_transient($this->getMemoryKey());
    }

    /**
     * Check if memory exists for this flow history.
     *
     * @return bool True if memory exists, false otherwise
     */
    public function exists(): bool
    {
        return get_transient($this->getMemoryKey()) !== false;
    }

    /**
     * Add a new message to existing memory.
     *
     * @param array $message The message to add
     *
     * @return bool True on success, false on failure
     */
    public function addMessage(array $message): bool
    {
        $messages = $this->retrieve();
        $messages[] = $message;

        return $this->store($messages);
    }

    /**
     * Get the number of messages in memory.
     *
     * @return int Number of messages
     */
    public function count(): int
    {
        return \count($this->retrieve());
    }

    /**
     * Get memory statistics.
     *
     * @return array Memory statistics
     */
    public function getStats(): array
    {
        $messages = $this->retrieve();
        $stats = [
            'total_messages'     => \count($messages),
            'user_messages'      => 0,
            'assistant_messages' => 0,
            'tool_calls'         => 0,
            'system_messages'    => 0,
        ];

        foreach ($messages as $message) {
            $role = $message['role'] ?? '';

            switch ($role) {
                case 'user':
                    ++$stats['user_messages'];

                    break;

                case 'assistant':
                    ++$stats['assistant_messages'];
                    if (isset($message['tool_calls'])) {
                        $stats['tool_calls'] += \count($message['tool_calls']);
                    }

                    break;

                case 'system':
                    ++$stats['system_messages'];

                    break;

                case 'tool':
                    // Tool responses are counted separately
                    break;
            }
        }

        return $stats;
    }

    /**
     * Get the memory key for this node.
     *
     * @return string The transient key
     */
    private function getMemoryKey(): string
    {
        return Config::VAR_PREFIX . $this->userDefinedKey . $this->nodeId;
    }
}
