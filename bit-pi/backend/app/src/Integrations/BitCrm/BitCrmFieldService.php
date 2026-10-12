<?php

namespace BitApps\Pi\src\Integrations\BitCrm;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use Throwable;

/**
 * Reads the field list of a module from Bit CRM itself. `{Module}Service::fields()`
 * hands back the shipped fields, the site's label and required overrides, and the
 * custom fields a Bit CRM Pro site defines, already merged.
 */
final class BitCrmFieldService
{
    public const TYPE_LOOKUP = 'lookup';

    public const TYPE_SELECT = 'select';

    private const SERVICES = [
        'lead'    => 'BitApps\Crm\Services\LeadService',
        'contact' => 'BitApps\Crm\Services\ContactService',
        'company' => 'BitApps\Crm\Services\CompanyService',
        'deal'    => 'BitApps\Crm\Services\DealService',
        'product' => 'BitApps\CrmPro\Services\ProductService',
    ];

    private const LOOKUP_TYPES = ['lookup_select', 'lookup_autocomplete'];

    private const GROUP_FIELDS_KEY = 'group_fields';

    /**
     * Every field Bit CRM reports for a module, flattened and normalised.
     *
     * @return array<int, array>
     */
    public static function fields(string $module): array
    {
        if (!isset(self::SERVICES[$module]) || !class_exists(self::SERVICES[$module])) {
            return [];
        }

        $service = self::SERVICES[$module];

        try {
            $fields = (new $service())->fields();
        } catch (Throwable) {
            return [];
        }

        return \is_array($fields) ? self::normalizeAll($fields) : [];
    }

    /**
     * Flatten Bit CRM's grouped field tree into plain rows.
     *
     * @return array<int, array>
     */
    private static function normalizeAll(array $fields): array
    {
        $normalized = [];

        foreach ($fields as $field) {
            $field = (array) $field;

            if (($field['type'] ?? '') === 'section') {
                continue;
            }

            if (!empty($field[self::GROUP_FIELDS_KEY]) && \is_array($field[self::GROUP_FIELDS_KEY])) {
                array_push($normalized, ...self::normalizeAll($field[self::GROUP_FIELDS_KEY]));

                continue;
            }

            $row = self::normalize($field);

            if ($row !== null) {
                $normalized[] = $row;
            }
        }

        return $normalized;
    }

    private static function normalize(array $field): ?array
    {
        $key = (string) ($field['field_key'] ?? '');

        if ($key === '') {
            return null;
        }

        $label = (string) ($field['label'] ?? '');

        $row = [
            'value'    => $key,
            'label'    => $label === '' ? $key : $label,
            'required' => !empty($field['required']),
            'type'     => (string) ($field['type'] ?? 'text'),
        ];

        if (!empty($field['is_custom'])) {
            // A custom field carries per-record data, so it stays a field map row
            // even when it has options of its own.
            if (empty($field['status'])) {
                return null;
            }

            $row['value'] = BitCrmHelper::CUSTOM_FIELD_PREFIX . $key;

            return $row;
        }

        if (\in_array($row['type'], self::LOOKUP_TYPES, true)) {
            $row['type'] = self::TYPE_LOOKUP;

            return $row;
        }

        $options = self::options($field);

        if (!empty($options)) {
            $row['type'] = self::TYPE_SELECT;
            $row['options'] = $options;
        }

        return $row;
    }

    /**
     * A field's value list, normalised to label/value strings.
     *
     * @return array<int, array>
     */
    private static function options(array $field): array
    {
        if (empty($field['options']) || !\is_array($field['options'])) {
            return [];
        }

        $options = [];

        foreach ($field['options'] as $option) {
            $option = (array) $option;
            $value = $option['value'] ?? $option['key'] ?? $option['id'] ?? '';

            if ($value === '' || $value === null || \is_array($value)) {
                continue;
            }

            $label = $option['label'] ?? $option['name'] ?? $option['title'] ?? $value;

            $options[] = [
                'label' => \is_array($label) ? self::toOptionValue($value) : self::toOptionValue($label),
                'value' => self::toOptionValue($value),
            ];
        }

        return $options;
    }

    /**
     * Bit CRM casts with boolval(), and boolval('false') is true, so a boolean
     * option value has to travel as '1'/'0'.
     */
    private static function toOptionValue(mixed $value): string
    {
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
