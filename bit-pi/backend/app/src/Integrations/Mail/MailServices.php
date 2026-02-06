<?php

namespace BitApps\Pi\src\Integrations\Mail;

use BitApps\Pi\Deps\BitApps\WPValidator\Validator;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Flow\NodeInfoProvider;

if (!\defined('ABSPATH')) {
    exit;
}



final class MailServices
{
    private NodeInfoProvider $nodeInfoProvider;

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
     * Parse and validate a single attachment path.
     *
     * @param string $attachment
     *
     * @return null|string
     */
    private function parseAttachment($attachment)
    {
        if (!\is_string($attachment) || trim($attachment) === '') {
            return;
        }

        $parseUrl = wp_parse_url($attachment);

        if (empty($parseUrl['path'])) {
            return;
        }

        $file = ABSPATH . ltrim($parseUrl['path'], '/');

        return file_exists($file) ? $file : null;
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
