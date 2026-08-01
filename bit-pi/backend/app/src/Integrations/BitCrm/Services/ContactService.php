<?php

namespace BitApps\Pi\src\Integrations\BitCrm\Services;

use BitApps\Crm\Model\Contact;
use BitApps\Pi\Deps\BitApps\WPKit\Hooks\Hooks;
use BitApps\Pi\src\Integrations\BitCrm\BitCrmHelper;
use BitApps\Pi\src\Integrations\IntegrationHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class ContactService extends BaseService
{
    public function createContact()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\ContactService')) {
            return $error;
        }

        $fields = $this->fields();
        $systemValues = $this->buildFieldValues(
            [
                'title'       => $fields['title'] ?? null,
                'lead_source' => $fields['lead_source'] ?? null,
                'company_id'  => $fields['company_id'] ?? null,
                'parent_id'   => $fields['parent_id'] ?? null,
                'owner_id'    => $fields['owner_id'] ?? null,
                'currency'    => $fields['currency'] ?? null,
            ]
        );

        $error = IntegrationHelper::validateFieldMap($systemValues, ['last_name' => ['required', 'string'], 'email' => ['nullable', 'email']]);
        if (!empty($error)) {
            return $error;
        }

        $payload = [
            'systemDefinedFieldsValues' => $systemValues,
            'tagIds'                    => BitCrmHelper::toIntArray($fields['tag_ids'] ?? []),
        ];

        $result = (new \BitApps\Crm\Services\ContactService())->store($payload);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function updateContact()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\ContactService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['contact_id' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        $systemValues = $this->buildFieldValues(
            [
                'title'       => $fields['title'] ?? null,
                'lead_source' => $fields['lead_source'] ?? null,
                'company_id'  => $fields['company_id'] ?? null,
                'parent_id'   => $fields['parent_id'] ?? null,
                'owner_id'    => $fields['owner_id'] ?? null,
                'currency'    => $fields['currency'] ?? null,
            ]
        );

        $payload = [
            'id'                        => (int) $fields['contact_id'],
            'systemDefinedFieldsValues' => $systemValues,
        ];

        $result = (new \BitApps\Crm\Services\ContactService())->update($payload);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function deleteContact()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\ContactService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['contact_id' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        $payload = ['ids' => [(int) $fields['contact_id']]];

        $result = (new \BitApps\Crm\Services\ContactService())->trash($payload);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function getAllContacts()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Contact')) {
            return $error;
        }

        $contacts = Contact::where('is_trash', 0)->get();

        return ['status_code' => 200, 'payload' => [], 'response' => $contacts ? $contacts->toArray() : []];
    }

    public function getContactById()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\ContactService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['contact_id' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        $payload = ['id' => (int) $fields['contact_id']];

        $result = (new \BitApps\Crm\Services\ContactService())->show($payload);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function getContactByEmail()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\ContactService')) {
            return $error;
        }

        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Contact')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['email' => ['required', 'email']]);
        if (!empty($error)) {
            return $error;
        }

        $email = $fields['email'];
        $payload = ['email' => $email];

        $contact = BitCrmHelper::normalizeData(Contact::findOne(['email' => $email, 'is_trash' => 0]));

        if (empty($contact['id'])) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('No contact found with this email.', 'bit-pi')];
        }

        $result = (new \BitApps\Crm\Services\ContactService())->show(['id' => (int) $contact['id']]);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function addTagToContact()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\ContactService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['contact_id' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        if (empty($fields['tag_ids']) && empty($fields['new_tags'])) {
            return ['status_code' => 400, 'payload' => [], 'response' => __('At least one existing or new tag must be provided.', 'bit-pi')];
        }

        $contactId = (int) $fields['contact_id'];
        $tagIds = BitCrmHelper::toIntArray($fields['tag_ids']);
        $newTags = array_values(array_filter((array) ($fields['new_tags'] ?? [])));

        $attached = (new \BitApps\Crm\Services\ContactService())->storeAndAttachTags($contactId, $tagIds, $newTags);

        $payload = ['contact_id' => $contactId, 'tag_ids' => $tagIds];

        if (!empty($attached)) {
            Hooks::doAction('bit_crm/tags_attached_to_contacts', $attached, [$contactId]);
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(Contact::findOne(['id' => $contactId]))];
    }

    public function removeTagFromContact()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\ContactService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['contact_id' => ['required'], 'tag_ids' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        $contactId = (int) $fields['contact_id'];
        $tagIds = BitCrmHelper::toIntArray($fields['tag_ids']);

        $detached = (new \BitApps\Crm\Services\ContactService())->detachTags($contactId, $tagIds);

        $payload = ['contact_id' => $contactId, 'tag_ids' => $tagIds];

        if (!$detached) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('No matching tags were removed from the contact.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(Contact::findOne(['id' => $contactId]))];
    }
}
