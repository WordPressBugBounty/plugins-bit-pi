<?php

namespace BitApps\Pi\src\Integrations\Mail;

use BitApps\Pi\Deps\BitApps\WPValidator\Validator;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Flow\NodeInfoProvider;

if (!defined('ABSPATH')) {
    exit;
}

final class MailServices
{
    private NodeInfoProvider $nodeInfoProvider;

    private array $tempFiles = [];

    /**
     * MailServices constructor.
     */
    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    /**
     * Send an email using WordPress wp_mail().
     *
     * @return array Response array with status and payload
     */
    public function sendEmail(): array
    {
        $fields = $this->nodeInfoProvider->getFieldMapData();
        $fields['mailFormat'] = (bool) ($this->nodeInfoProvider->getFieldMapConfigs('mail-format.value') ?? false);

        $validator = new Validator();
        $rules = [
            'fromEmail' => ['required', 'email'],
            'toEmail'   => ['required', 'email'],
            'fromName'  => ['required'],
            'subject'   => ['required'],
            'body'      => ['required'],
        ];

        $validation = $validator->make($fields, $rules);

        if ($validation->fails()) {
            return Utility::formatResponseData(422, $fields, $validation->errors());
        }

        $to = $this->arrayToString($fields['toEmail']);
        $headers = $this->buildMailHeaders($fields);
        $attachments = $this->sanitizeAttachments($fields['attachments']);

        $status = wp_mail(
            $to,
            $fields['subject'],
            $fields['body'],
            $headers,
            $attachments
        );

        $this->cleanupTempFiles();

        if (!$status) {
            return Utility::formatResponseData(500, $fields, __('Failed to send email', 'bit-pi'));
        }

        return Utility::formatResponseData(200, $fields, __('Email sent successfully', 'bit-pi'));
    }

    /**
     * Build mail headers for wp_mail.
     */
    private function buildMailHeaders(array $fields): array
    {
        $headers = [];

        if (!empty($fields['fromEmail']) && !empty($fields['fromName'])) {
            $headers[] = 'From: ' . $fields['fromName'] . ' <' . $fields['fromEmail'] . '>';
        } elseif (!empty($fields['fromEmail'])) {
            $headers[] = 'From: ' . $fields['fromEmail'];
        }

        if (!empty($fields['replyToEmail'])) {
            $headers[] = 'Reply-To: ' . $this->arrayToString($fields['replyToEmail']);
        }

        if (!empty($fields['ccEmail'])) {
            $headers[] = 'Cc: ' . $this->arrayToString($fields['ccEmail']);
        }

        if (!empty($fields['bccEmail'])) {
            $headers[] = 'Bcc: ' . $this->arrayToString($fields['bccEmail']);
        }

        $headers[] = !empty($fields['mailFormat']) && $fields['mailFormat']
            ? 'Content-Type: text/html; charset=UTF-8'
            : 'Content-Type: text/plain; charset=UTF-8';

        return $headers;
    }

    /**
     * Sanitize and normalize attachments for wp_mail.
     *
     * @param mixed $attachments
     */
    private function sanitizeAttachments($attachments): array
    {
        if (empty($attachments)) {
            return [];
        }

        $result = [];
        $files = \is_array($attachments) ? $attachments : array_map('trim', explode(',', $attachments));

        foreach ($files as $file) {
            $parsed = $this->parseAttachment($file);

            if ($parsed) {
                $result[] = $parsed;
            }
        }

        return $result;
    }

    /**
     * Parse and validate a single attachment.
     *
     * @param string $attachment
     *
     * @return null|string absolute file path ready for wp_mail, or null to skip
     */
    private function parseAttachment($attachment)
    {
        if (!\is_string($attachment) || trim($attachment) === '') {
            return;
        }

        $attachment = trim($attachment);
        $uploadDir = wp_upload_dir();

        if (empty($uploadDir['baseurl']) || empty($uploadDir['basedir'])) {
            return;
        }

        if (str_starts_with($attachment, $uploadDir['baseurl'])) {
            $file = $uploadDir['basedir'] . substr($attachment, \strlen($uploadDir['baseurl']));
        } elseif (str_starts_with($attachment, $uploadDir['basedir'])) {
            $file = $attachment;
        } elseif (filter_var($attachment, FILTER_VALIDATE_URL) && wp_http_validate_url($attachment)) {
            return $this->downloadExternalFile($attachment);
        } else {
            return;
        }

        $realFile = realpath($file);
        $realUploads = realpath($uploadDir['basedir']);

        if ($realFile === false || $realUploads === false) {
            return;
        }

        if (!str_starts_with($realFile, $realUploads . \DIRECTORY_SEPARATOR)) {
            return;
        }

        return file_exists($realFile) ? $realFile : null;
    }

    /**
     * Download an external URL to a named temp file and return its path.
     * The file is tracked in $this->tempFiles and removed by cleanupTempFiles()
     * after wp_mail returns, so it is never sideloaded into the media library.
     *
     * @param string $url the URL of the file to download
     *
     * @return null|string the local temp file path if successful, or null on failure
     */
    private function downloadExternalFile(string $url): ?string
    {
        $tmp = wp_tempnam($url);

        if ($tmp === false) {
            return null;
        }

        $response = wp_safe_remote_get(
            $url,
            [
                'timeout'             => 10,
                'stream'              => true,
                'filename'            => $tmp,
                'reject_unsafe_urls'  => true,
                'limit_response_size' => 10 * MB_IN_BYTES,
            ]
        );

        if (is_wp_error($response)) {
            wp_delete_file($tmp);

            return null;
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);

        if ($statusCode < 200 || $statusCode >= 300 || !file_exists($tmp)) {
            wp_delete_file($tmp);

            return null;
        }

        $filename = sanitize_file_name(basename((string) wp_parse_url($url, PHP_URL_PATH)));

        if (empty($filename)) {
            $filename = 'attachment-' . uniqid('', true);
        }

        // Rename to a meaningful and unique filename so PHPMailer picks up the
        // correct extension, recipients see a readable name, and temp collisions
        // do not overwrite files from concurrent/same-name attachments.
        $tmpDir = \dirname($tmp);
        $uniqueFilename = wp_unique_filename($tmpDir, $filename);
        $namedTmp = $tmpDir . \DIRECTORY_SEPARATOR . $uniqueFilename;

        if ($namedTmp !== $tmp && rename($tmp, $namedTmp)) {
            $this->tempFiles[] = $namedTmp;

            return $namedTmp;
        }

        // Fallback: keep the original tmp path if rename failed.
        $this->tempFiles[] = $tmp;

        return $tmp;
    }

    /**
     * Delete all temporary files that were downloaded for external attachments.
     */
    private function cleanupTempFiles(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                wp_delete_file($file);
            }
        }

        $this->tempFiles = [];
    }

    /**
     * Convert an array to a comma-separated string.
     * Accepts either an array or a string.
     */
    private function arrayToString(array|string $data): string
    {
        return \is_string($data) ? $data : implode(', ', array_map('trim', $data));
    }
}
