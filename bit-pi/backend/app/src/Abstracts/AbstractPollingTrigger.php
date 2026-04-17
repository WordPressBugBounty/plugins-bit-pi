<?php

namespace BitApps\Pi\src\Abstracts;

abstract class AbstractPollingTrigger
{
    public const TYPE_NEW = 'NEW';

    public const TYPE_UPDATED = 'UPDATED';

    /**
     * Get the polling type.
     *
     * @return string the type of polling (self::TYPE_NEW or self::TYPE_UPDATED)
     */
    public function getPollingType(): string
    {
        return self::TYPE_NEW;
    }

    /**
     * Get the unique field name used for polling.
     *
     * @return string the name of the unique identifier field in the polled data (default: 'id')
     */
    public function getPollingUniqueFieldName(): string
    {
        return 'id';
    }

    /**
     * Poll for new or updated data.
     * This method should be implemented by subclasses to perform the actual polling logic.
     *
     * @return array the polled data records
     */
    abstract public function poll(): array;
}
