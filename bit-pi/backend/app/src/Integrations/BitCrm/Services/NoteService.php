<?php

namespace BitApps\Pi\src\Integrations\BitCrm\Services;

use BitApps\Crm\Model\Note;
use BitApps\Pi\Deps\BitApps\WPKit\Hooks\Hooks;
use BitApps\Pi\src\Integrations\BitCrm\BitCrmHelper;
use BitApps\Pi\src\Integrations\IntegrationHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class NoteService extends BaseService
{
    public function createNote()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\NoteService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap(
            $fields,
            [
                'entity_id' => ['required', 'integer'],
                'module'    => ['required', 'string'],
                'title'     => ['required', 'string'],
            ]
        );
        if (!empty($error)) {
            return $error;
        }

        $payload = [
            'title'     => $fields['title'],
            'details'   => $fields['details'] ?? '',
            'entity_id' => (int) $fields['entity_id'],
            'module'    => $fields['module'],
            'is_shared' => !empty($fields['is_shared']),
        ];

        $result = (new \BitApps\Crm\Services\NoteService())->store($payload);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function updateNote()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Note')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['note_id' => ['required', 'integer']]);
        if (!empty($error)) {
            return $error;
        }

        $noteId = (int) $fields['note_id'];
        $payload = ['id' => $noteId];

        $note = Note::findOne(['id' => $noteId]);
        if (empty($note)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('Note not found.', 'bit-pi')];
        }

        $isShared = !empty($fields['is_shared']);

        // Sharing a note requires the linked contact to have a portal account
        // with notes enabled, so let Bit CRM run that check first.
        if ($isShared && class_exists('BitApps\Crm\Services\NoteService')) {
            $validation = (new \BitApps\Crm\Services\NoteService())->validateSharedNote((int) $note->entity_id);

            if (($validation['success'] ?? false) === false) {
                return ['status_code' => 400, 'payload' => $payload, 'response' => $validation['errors'][0] ?? __('This note cannot be shared.', 'bit-pi')];
            }
        }

        $updateData = ['is_shared' => $isShared, 'updated_by' => get_current_user_id()];

        foreach (['title', 'details'] as $field) {
            if (isset($fields[$field]) && $fields[$field] !== '') {
                $updateData[$field] = $fields[$field];
            }
        }

        $payload = array_merge($payload, $updateData);

        if (!$note->update($updateData)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('Failed to update note.', 'bit-pi')];
        }

        Hooks::doAction('bit_crm/note_updated', $note);

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData($note)];
    }

    public function deleteNote()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Note')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['note_id' => ['required', 'integer']]);
        if (!empty($error)) {
            return $error;
        }

        $noteId = (int) $fields['note_id'];
        $payload = ['id' => $noteId];

        $note = Note::findOne(['id' => $noteId]);
        if (empty($note)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('Note not found.', 'bit-pi')];
        }

        $deletedNote = BitCrmHelper::normalizeData($note);

        if (!$note->delete()) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('Failed to delete note.', 'bit-pi')];
        }

        Hooks::doAction('bit_crm/note_deleted', $noteId);

        return ['status_code' => 200, 'payload' => $payload, 'response' => $deletedNote];
    }

    public function getAllNotes()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Note')) {
            return $error;
        }

        $fields = $this->fields();
        $module = $fields['module'] ?? '';
        $entityId = (int) ($fields['entity_id'] ?? 0);

        $payload = ['module' => $module, 'entity_id' => $entityId];

        if (empty($module)) {
            $notes = Note::all();
        } else {
            $query = Note::where('module', $module);

            if (!empty($entityId)) {
                $query = $query->where('entity_id', $entityId);
            }

            $notes = $query->get();
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => $notes ? $notes->toArray() : []];
    }

    public function getNoteById()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Note')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['note_id' => ['required', 'integer']]);
        if (!empty($error)) {
            return $error;
        }

        $noteId = (int) $fields['note_id'];
        $payload = ['id' => $noteId];

        $note = BitCrmHelper::normalizeData(Note::findOne(['id' => $noteId]));

        if (empty($note)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('Note not found.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => $note];
    }
}
