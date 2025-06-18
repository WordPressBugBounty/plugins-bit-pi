<?php

namespace BitApps\Pi\Helpers;

use BitApps\Pi\Config;
use Exception;

if (!\defined('ABSPATH')) {
    exit;
}

class Utility
{
    /**
     * Gets a value from an array using a path.
     *
     * @param array  $data the array to get the value from
     * @param string $path the path to the value
     *
     * @return mixed the value
     */
    public static function getValueFromPath($data, $path)
    {
        if (empty($data)) {
            return $data;
        }

        if (!\is_null($path) && $path !== '') {
            $keys = explode('.', $path);

            foreach ($keys as $key) {
                $data = \is_object($data) ? (array) $data : $data;

                if (\array_key_exists($key, $data)) {
                    $data = $data[$key];
                }
            }
        }

        return $data;
    }

    public static function formatResponseData($statusCode, $requestBody, $response, $message = null)
    {
        if ($statusCode >= 200 && $statusCode < 300) {
            return [
                'status'  => 'success',
                'message' => $message,
                'output'  => $response ?? [],
                'input'   => $requestBody ?? [],
            ];
        }

        return [
            'status'  => 'error',
            'message' => $message,
            'output'  => $response,
            'input'   => $requestBody,
        ];
    }

    /**
     * Checks if the given array is a multi-dimensional array.
     *
     * @param array $data
     *
     * @return bool whether the array is a multi-dimensional array
     */
    public static function isMultiDimensionArray($data)
    {
        if (!\is_array($data) || $data === []) {
            return false;
        }

        $arrayValuesWithIntegerKeys = array_filter(
            $data,
            fn ($val, $key) => (\is_array($val) || \is_object($val)) && \is_int($key),
            ARRAY_FILTER_USE_BOTH
        );

        return \count($arrayValuesWithIntegerKeys) === \count($data);
    }

    /**
     * Get file path in wordpress.
     *
     * @param mixed $file
     */
    public static function getFilePath($file)
    {
        $fileUploadBaseUrl = Config::get('UPLOAD_BASE_URL');

        $fileUploadBaseDir = Config::get('UPLOAD_BASE_DIR');

        if (\is_array($file)) {
            $path = [];

            foreach ($file as $fileIndex => $fileUrl) {
                $path[$fileIndex] = str_replace($fileUploadBaseUrl, $fileUploadBaseDir, $fileUrl);
            }
        } else {
            $path = str_replace($fileUploadBaseUrl, $fileUploadBaseDir, $file);
        }

        return $path;
    }

    /**
     * Get user info.
     *
     * @param int $userId
     *
     * @return array
     */
    public static function getUserInfo($userId)
    {
        $userInfo = get_userdata($userId);

        if (!$userInfo) {
            return [];
        }

        $userData = $userInfo->data;

        $userMeta = get_user_meta($userId);

        return [
            'user_id'      => $userId,
            'first_name'   => $userMeta['first_name'][0],
            'last_name'    => $userMeta['last_name'][0],
            'user_email'   => $userData->user_email,
            'nickname'     => $userData->user_nicename,
            'avatar_url'   => get_avatar_url($userId),
            'display_name' => $userData->display_name,
            'user_pass'    => $userData->user_pass,
            'user_roles'   => $userInfo->roles
        ];
    }

    /**
     * Replace dots (.) with colons (:) in array keys.
     *
     * This function iterates over an associative array and replaces any dots (.)
     * in the keys with colons (:). It ensures that the modified keys retain their
     * respective values while removing the original keys.
     *
     * @param array $data the input associative array with keys that may contain dots
     *
     * @return array the updated array with dots in keys replaced by colons
     */
    public static function convertDotKeysToColons($data)
    {
        foreach ($data as $key => $value) {
            if (strpos($key, '.') !== false) {
                $updatedKey = str_replace('.', ':', $key);
                $data[$updatedKey] = $value;
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Get transient cache by key name.
     *
     * @param mixed $keyName
     *
     * @return mixed
     */
    public static function getTransientCache($keyName)
    {
        if (empty($keyName)) {
            return false;
        }

        $keyName = Config::VAR_PREFIX . $keyName;

        return get_transient($keyName);
    }

    /**
     * Set transient cache by key name.
     *
     * @param mixed $keyName
     * @param mixed $data
     * @param int   $expiration
     *
     * @throws Exception
     *
     * @return bool
     */
    public static function setTransientCache($keyName, $data, $expiration = 3600)
    {
        if (empty($keyName)) {
            return false;
        }

        $keyName = Config::VAR_PREFIX . $keyName;

        return set_transient($keyName, $data, $expiration);
    }

    /**
     * Delete transient cache by key name.
     *
     * @param mixed $keyName
     */
    public static function deleteTransientCache($keyName)
    {
        if (empty($keyName)) {
            return false;
        }

        $keyName = Config::VAR_PREFIX . $keyName;

        return delete_transient($keyName);
    }

    /**
     * Convert a hook name to a machine-readable slug.
     *
     * This function takes a hook name in the format "hook_name" and converts it
     * to a machine-readable slug by replacing underscores with spaces, capitalizing
     * each word, and then removing spaces. The first letter of the resulting string
     * is converted to lowercase.
     *
     * @param string $hookName the hook name to convert
     *
     * @return string the machine-readable slug
     */
    public static function convertToMachineSlug($hookName)
    {
        $formatted = str_replace(' ', '', ucwords(str_replace('_', ' ', $hookName)));

        return lcfirst($formatted);
    }

    /**
     * Get the first N items from the given array.
     *
     * This helper function safely returns up to `$limit` elements from the start of the array.
     * If the array has fewer than `$limit` elements, it returns all of them.
     *
     * @param array $array the input array
     * @param int $limit The maximum number of items to return. Default is 10.
     *
     * @return array the sliced portion of the array
     */
    public static function getFirstItems(array $array, int $limit = 10): array
    {
        return \array_slice($array, 0, $limit);
    }
}
