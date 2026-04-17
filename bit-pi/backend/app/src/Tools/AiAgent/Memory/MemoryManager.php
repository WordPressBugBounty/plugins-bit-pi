<?php

namespace BitApps\Pi\src\Tools\AiAgent\Memory;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use Exception;

/**
 * Memory Manager (Factory) for AI Agent memory implementations.
 *
 * Provides a central point to create and manage different memory types.
 * Follows the Open/Closed Principle - new memory types can be added
 * without modifying existing code.
 */
class MemoryManager
{
    /**
     * Registry of available memory types.
     *
     * @var array<string, string> Map of memory slug to class name
     */
    private static $memoryTypes = [
        'simpleMemory' => SimpleMemory::class,
    ];

    /**
     * Create a memory instance based on the memory slug.
     *
     * @param string $memorySlug The memory type slug (e.g., 'simple', 'database')
     * @param string $nodeId     The node ID for memory storage (used as memory key)
     * @param array  $config     Optional configuration for the memory instance
     *
     * @throws Exception If memory type is not found or class doesn't exist
     *
     * @return MemoryInterface The memory instance
     */
    public static function create(string $memorySlug, string $nodeId, array $config = []): MemoryInterface
    {
        if (!isset(self::$memoryTypes[$memorySlug])) {
            throw new Exception(esc_html("Memory type '{$memorySlug}' is not registered. Available types: " . implode(', ', array_keys(self::$memoryTypes))));
        }

        $className = self::$memoryTypes[$memorySlug];

        // Check if class exists
        if (!class_exists($className)) {
            throw new Exception(esc_html("Memory class '{$className}' does not exist."));
        }

        // Create and return the memory instance
        $instance = new $className($nodeId, $config);

        // Verify it implements the interface
        if (!$instance instanceof MemoryInterface) {
            throw new Exception(esc_html("Memory class '{$className}' must implement MemoryInterface."));
        }

        return $instance;
    }

    /**
     * Register a new memory type.
     *
     * Allows plugins/extensions to add custom memory implementations.
     *
     * @param string $slug      The memory type slug (e.g., 'redis', 'database')
     * @param string $className The fully qualified class name
     *
     * @throws Exception If slug is already registered or class doesn't exist
     */
    public static function register(string $slug, string $className): void
    {
        $slug = strtolower(trim($slug));

        if (isset(self::$memoryTypes[$slug])) {
            throw new Exception(esc_html("Memory type '{$slug}' is already registered."));
        }

        if (!class_exists($className)) {
            throw new Exception(esc_html("Memory class '{$className}' does not exist."));
        }

        self::$memoryTypes[$slug] = $className;
    }

    /**
     * Unregister a memory type.
     *
     * @param string $slug The memory type slug to unregister
     *
     * @return bool True if unregistered, false if not found
     */
    public static function unregister(string $slug): bool
    {
        $slug = strtolower(trim($slug));

        if (isset(self::$memoryTypes[$slug])) {
            unset(self::$memoryTypes[$slug]);

            return true;
        }

        return false;
    }

    /**
     * Get all registered memory types.
     *
     * @return array<string, string> Map of slug to class name
     */
    public static function getRegisteredTypes(): array
    {
        return self::$memoryTypes;
    }

    /**
     * Check if a memory type is registered.
     *
     * @param string $slug The memory type slug
     *
     * @return bool True if registered, false otherwise
     */
    public static function isRegistered(string $slug): bool
    {
        $slug = strtolower(trim($slug));

        return isset(self::$memoryTypes[$slug]);
    }

    /**
     * Get the class name for a memory type.
     *
     * @param string $slug The memory type slug
     *
     * @return null|string The class name or null if not found
     */
    public static function getClassName(string $slug): ?string
    {
        $slug = strtolower(trim($slug));

        return self::$memoryTypes[$slug] ?? null;
    }
}
