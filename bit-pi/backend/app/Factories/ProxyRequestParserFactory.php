<?php

namespace BitApps\Pi\Factories;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Helpers\Hash;
use BitApps\Pi\Helpers\Utility;

/**
 * Class ProxyRequestParserFactory
 * Handles the parsing and encryption/decryption of request data.
 */
class ProxyRequestParserFactory
{
    private const ENCRYPTION_TYPES = [
        'base64_encode',
        'base64_decode',
        'hmac_decrypt',
        'hmac_encrypt',
        'sha256',
        'base64_urlencode',
    ];

    /**
     * Parses the given request array and processes headers, query parameters, and body parameters.
     *
     * @param array $request the request array containing headers, queryParams, and bodyParams
     *
     * @return array the parsed request array
     */
    public static function parse(array $request): array
    {
        if (isset($request['headers'])) {
            $request['headers'] = self::parseArrayValue($request['headers']);
        }

        if (isset($request['queryParams'])) {
            $request['queryParams'] = self::parseArrayValue($request['queryParams']);
        }

        if (isset($request['bodyParams'])) {
            $request['bodyParams'] = self::parseArrayValue($request['bodyParams']);
        }

        return $request;
    }

    /**
     * Recursively parses array values, processing encryption if specified.
     *
     * @param mixed $data the data to be parsed (array or scalar)
     *
     * @return mixed the parsed data
     */
    private static function parseArrayValue($data)
    {
        if (!\is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $item) {
            if (!\is_array($item)) {
                continue;
            }

            if (Utility::isSequentialArray($item) && self::hasEncryptionKeyAndValidType($item)) {
                $data[$key] = implode('', self::parseArrayValue($item));

                continue;
            }

            if (isset($item['encryption'])) {
                $data[$key] = self::processEncryption(self::parseArrayValue($item));
            }
        }

        return $data;
    }

    /**
     * Processes encryption or decryption based on the specified encryption type.
     *
     * @param array $data the data array containing 'encryption' type and 'value'
     *
     * @return mixed the encrypted or decrypted value, or the original value if encryption type is unknown
     */
    private static function processEncryption(array $data)
    {
        $value = $data['value'];

        switch ($data['encryption']) {
            case 'base64_encode':
                return base64_encode($value);

            case 'base64_decode':
                return base64_decode($value);

            case 'hmac_decrypt':
                return Hash::decrypt($value);

            case 'hmac_encrypt':
                return Hash::encrypt($value);

            case 'sha256':
                return hash('sha256', $value);

            case 'base64_urlencode':
                return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

            default:
                return $value;
        }
    }

    private static function hasEncryptionKeyAndValidType(array $items): bool
    {
        foreach ($items as $item) {
            if (
                \is_array($item)
                && \array_key_exists('encryption', $item)
                && \in_array($item['encryption'], self::ENCRYPTION_TYPES, true)
            ) {
                return true;
            }
        }

        return false;
    }
}
