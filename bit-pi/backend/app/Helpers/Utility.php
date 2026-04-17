<?php

namespace BitApps\Pi\Helpers;

use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class Utility
{
    /**
     * Retrieve a value from a nested array or object using dot notation.
     *
     * Example:
     * $data = [
     *     'address' => [
     *         'state' => 'state-01'
     *     ]
     * ];
     * getValueFromPath($data, 'address.state'); // Returns: 'state-01'
     *
     * @param mixed  $data the input data (array or object)
     * @param string $path Dot-notated path to the target value (e.g., "address.state").
     *
     * @return mixed the value if found; otherwise, an empty string
     */
    public static function getValueFromPath($data, $path)
    {
        if (empty($data) || (!is_numeric($path) && empty($path))) {
            return $data;
        }

        $keys = explode('.', $path);

        $current = $data;

        foreach ($keys as $key) {
            $current = \is_object($current) ? (array) $current : $current;

            if (\is_array($current) && \array_key_exists($key, $current)) {
                $current = $current[$key];
            } else {
                return '';
            }
        }

        return $current;
    }

    public static function formatResponseData($statusCode, $requestBody, $response, $message = null)
    {
        if (!\is_array($response) && !\is_object($response)) {
            $response = [$response];
        }

        if (!\is_array($requestBody) && !\is_object($requestBody)) {
            $requestBody = [$requestBody];
        }

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
        $user = get_userdata($userId);

        return $user ? self::formatUserData($user) : [];
    }

    /**
     * Get user info by field.
     *
     * @param string $field
     * @param string $value
     *
     * @return array
     */
    public static function getUserDataByField($field, $value)
    {
        $user = get_user_by($field, $value);

        return $user ? self::formatUserData($user) : [];
    }

    /**
     * Get all users.
     *
     * @param array $args
     *
     * @return array
     */
    public static function getUsers($args = [])
    {
        $users = get_users($args);

        if (empty($users)) {
            return [];
        }

        return array_map([self::class, 'formatUserData'], $users);
    }

    /**
     * Get User Metadata.
     *
     * @param int $userId
     * @param string $key
     * @param bool $single
     *
     * @return array
     */
    public static function getUserMetadata($userId, $key = '', $single = false)
    {
        $metadata = get_user_meta($userId, $key, $single);

        return self::sanitizeMetadata($metadata, $key, $single);
    }

    /**
     * Get Post Metadata.
     *
     * @param mixed $postId
     * @param string $key
     * @param bool $single
     *
     * @return array
     */
    public static function getPostMetadata($postId, $key = '', $single = false)
    {
        $metadata = get_post_meta($postId, $key, $single);

        return self::sanitizeMetadata($metadata, $key, $single);
    }

    /**
     * Get Comment Metadata.
     *
     * @param mixed $commentId
     * @param string $key
     * @param bool $single
     *
     * @return array
     */
    public static function getCommentMetadata($commentId, $key = '', $single = false)
    {
        $metadata = get_comment_meta($commentId, $key, $single);

        return self::sanitizeMetadata($metadata, $key, $single);
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

    /**
     * Check if the given array is a sequential array.
     *
     * A sequential array is an array where the keys are integers starting from 0
     * and incrementing by 1 for each element.
     *
     * @param array $array the array to check
     *
     * @return bool true if the array is sequential, false otherwise
     */
    public static function isSequentialArray($array)
    {
        if (!\is_array($array)) {
            return false;
        }

        return array_keys($array) === range(0, \count($array) - 1);
    }

    /**
     * Convert string to array.
     *
     * @param null|array|string $data
     * @param string $separator
     *
     * @return array
     */
    public static function convertStringToArray($data, $separator = ',')
    {
        if (empty($data)) {
            return [];
        }

        if (\is_array($data)) {
            return array_map('trim', $data);
        }

        return array_map('trim', explode($separator, $data));
    }

    public static function jsonEncodeDecode($data)
    {
        return JSON::decode(JSON::encode($data));
    }

    public static function clearFlowSchedules()
    {
        $scheduleNodes = (array) Utility::getTransientCache('schedules');

        foreach ($scheduleNodes as $nodeId => $scheduleNode) {
            $schedule = JSON::decode($scheduleNode ?? '', true);


            if (empty($schedule)) {
                continue;
            }

            $hookArgs = [
                'nodeId'     => $nodeId,
                'dayOfMonth' => null,
                'day'        => null,
            ];

            foreach ($schedule as $index => $item) {
                if (!empty($item['interval'])) {
                    $actionHook = Config::VAR_PREFIX . "flow_schedule_event_{$nodeId}_{$index}";
                    wp_clear_scheduled_hook($actionHook, $hookArgs);
                }
            }
        }
    }

    /**
     * Sanitize Metadata.
     *
     * @param null|array $metadata
     * @param string $key
     * @param bool $single
     *
     * @return array
     */
    private static function sanitizeMetadata($metadata, $key = '', $single = false)
    {
        if (!empty($key) && $single) {
            return [
                'meta_key'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Preparing data for a meta query, not executing it.
                'meta_value' => $metadata, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Preparing data for a meta query, not executing it.
            ];
        }

        if (empty($metadata)) {
            return [];
        }

        return array_map(
            function ($value) {
                return maybe_unserialize(reset($value));

                if (\is_array($value)) {
                    return JSON::maybeEncode($value);
                }
            },
            $metadata
        );
    }

    /**
     * Format user object into a clean array.
     *
     * @param WP_User $user
     *
     * @return array
     */
    private static function formatUserData($user)
    {
        $userData = $user->data;
        $userId = $userData->ID;

        $userMeta = get_user_meta($userId);

        return [
            'user_id'      => $userId,
            'first_name'   => $userMeta['first_name'][0],
            'last_name'    => $userMeta['last_name'][0],
            'user_login'   => $userData->user_login,
            'user_email'   => $userData->user_email,
            'nickname'     => $userData->user_nicename,
            'avatar_url'   => get_avatar_url($userId),
            'display_name' => $userData->display_name,
            'user_roles'   => $user->roles
        ];
    }
}
