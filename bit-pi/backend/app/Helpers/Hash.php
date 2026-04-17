<?php

namespace BitApps\Pi\Helpers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;

class Hash
{
    public const CIPHER = 'aes-256-cbc';

    public static function encrypt($data)
    {
        $secretKey = Config::getOption('secret_key');

        if (!$secretKey) {
            $secretKey = Config::VAR_PREFIX . time();
            Config::addOption('secret_key', $secretKey, true);
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = openssl_random_pseudo_bytes($ivLength);
        $cipherText = openssl_encrypt($data, self::CIPHER, $secretKey, 0, $iv);

        return urlencode($iv . $cipherText);
    }

    public static function decrypt($encryptedData)
    {
        $secretKey = Config::getOption('secret_key');

        if (!$secretKey) {
            $secretKey = Config::VAR_PREFIX . time();
            Config::addOption('secret_key', $secretKey, true);
        }

        $decode = urldecode($encryptedData);
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = substr($decode, 0, $ivLength);
        $cipherText = substr($decode, $ivLength);

        return openssl_decrypt($cipherText, self::CIPHER, $secretKey, 0, $iv);
    }
}
