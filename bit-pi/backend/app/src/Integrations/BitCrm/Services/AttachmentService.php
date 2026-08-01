<?php

namespace BitApps\Pi\src\Integrations\BitCrm\Services;

use BitApps\Crm\Model\Attachment;
use BitApps\Pi\src\Integrations\BitCrm\BitCrmHelper;
use BitApps\Pi\src\Integrations\IntegrationHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Read-only access to the files Bit CRM keeps against leads, contacts,
 * companies and deals. Bit CRM has no public write API for attachments, so
 * uploads stay out of scope.
 */
final class AttachmentService extends BaseService
{
    public function getAllAttachments()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Attachment')) {
            return $error;
        }

        $fields = $this->fields();
        $module = $fields['module'] ?? '';
        $entityId = (int) ($fields['entity_id'] ?? 0);

        $payload = ['module' => $module, 'entity_id' => $entityId];

        if (empty($module)) {
            $attachments = Attachment::all();
        } else {
            $query = Attachment::where('module', $module);

            if (!empty($entityId)) {
                $query = $query->where('entity_id', $entityId);
            }

            $attachments = $query->get();
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => $attachments ? $attachments->toArray() : []];
    }

    public function getAttachmentById()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Attachment')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['attachment_id' => ['required', 'integer']]);
        if (!empty($error)) {
            return $error;
        }

        $attachmentId = (int) $fields['attachment_id'];
        $payload = ['id' => $attachmentId];

        $attachment = BitCrmHelper::normalizeData(Attachment::findOne(['id' => $attachmentId]));

        if (empty($attachment)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('Attachment not found.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => $attachment];
    }
}
