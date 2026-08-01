<?php

namespace BitApps\Pi\src\Integrations\BitCrm\Services;

use BitApps\Crm\Deps\BitApps\WPDatabase\Connection;
use BitApps\Crm\Model\Lead;
use BitApps\Crm\Services\ConvertService;
use BitApps\Crm\Services\LeadConvertService;
use BitApps\Pi\Deps\BitApps\WPKit\Hooks\Hooks;
use BitApps\Pi\src\Integrations\BitCrm\BitCrmHelper;
use BitApps\Pi\src\Integrations\IntegrationHelper;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

final class LeadService extends BaseService
{
    public function createLead()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\LeadService')) {
            return $error;
        }

        $fields = $this->fields();
        $systemValues = $this->buildFieldValues(
            [
                'title'       => $fields['title'] ?? null,
                'lead_source' => $fields['lead_source'] ?? null,
                'lead_status' => $fields['lead_status'] ?? null,
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

        $result = (new \BitApps\Crm\Services\LeadService())->store($payload);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function updateLead()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\LeadService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['lead_id' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        $systemValues = $this->buildFieldValues(
            [
                'title'       => $fields['title'] ?? null,
                'lead_source' => $fields['lead_source'] ?? null,
                'lead_status' => $fields['lead_status'] ?? null,
                'owner_id'    => $fields['owner_id'] ?? null,
                'currency'    => $fields['currency'] ?? null,
            ]
        );

        $payload = [
            'id'                        => (int) $fields['lead_id'],
            'systemDefinedFieldsValues' => $systemValues,
        ];

        $result = (new \BitApps\Crm\Services\LeadService())->update($payload);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function deleteLead()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\LeadService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['lead_id' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        $payload = ['ids' => [(int) $fields['lead_id']]];

        $result = (new \BitApps\Crm\Services\LeadService())->trash($payload);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function getAllLeads()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Lead')) {
            return $error;
        }

        $leads = Lead::where('is_trash', 0)->where('is_converted', 0)->get();

        return ['status_code' => 200, 'payload' => [], 'response' => $leads ? $leads->toArray() : []];
    }

    public function getLeadById()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\LeadService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['lead_id' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        $payload = ['id' => (int) $fields['lead_id']];

        $result = (new \BitApps\Crm\Services\LeadService())->show($payload);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function getLeadByEmail()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\LeadService')) {
            return $error;
        }

        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Lead')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['email' => ['required', 'email']]);
        if (!empty($error)) {
            return $error;
        }

        $email = $fields['email'];
        $payload = ['email' => $email];

        $lead = BitCrmHelper::normalizeData(Lead::findOne(['email' => $email, 'is_trash' => 0, 'is_converted' => 0]));

        if (empty($lead['id'])) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('No lead found with this email.', 'bit-pi')];
        }

        $result = (new \BitApps\Crm\Services\LeadService())->show(['id' => (int) $lead['id']]);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function addTagToLead()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\LeadService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['lead_id' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        if (empty($fields['tag_ids']) && empty($fields['new_tags'])) {
            return ['status_code' => 400, 'payload' => [], 'response' => __('At least one existing or new tag must be provided.', 'bit-pi')];
        }

        $leadId = (int) $fields['lead_id'];
        $tagIds = BitCrmHelper::toIntArray($fields['tag_ids']);
        $newTags = array_values(array_filter((array) ($fields['new_tags'] ?? [])));

        $attached = (new \BitApps\Crm\Services\LeadService())->storeAndAttachTags($leadId, $tagIds, $newTags);

        $payload = ['lead_id' => $leadId, 'tag_ids' => $tagIds];

        if (!empty($attached)) {
            Hooks::doAction('bit_crm/tags_attached_to_leads', $attached, [$leadId]);
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(Lead::findOne(['id' => $leadId]))];
    }

    public function removeTagFromLead()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\LeadService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['lead_id' => ['required'], 'tag_ids' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        $leadId = (int) $fields['lead_id'];
        $tagIds = BitCrmHelper::toIntArray($fields['tag_ids']);

        $detached = (new \BitApps\Crm\Services\LeadService())->detachTags($leadId, $tagIds);

        $payload = ['lead_id' => $leadId, 'tag_ids' => $tagIds];

        if (!$detached) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('No matching tags were removed from the lead.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(Lead::findOne(['id' => $leadId]))];
    }

    public function convertLead()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\LeadConvertService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap(
            $fields,
            [
                'lead_id'              => ['required', 'integer'],
                'convert_to'           => ['required'],
                'move_related_data_to' => ['required', 'string'],
            ]
        );
        if (!empty($error)) {
            return $error;
        }

        $leadId = (int) $fields['lead_id'];
        $convertTo = array_values(array_filter((array) $fields['convert_to']));
        $moveRelatedDataTo = $fields['move_related_data_to'];
        $defaultOwnerId = !empty($fields['owner_id']) ? (int) $fields['owner_id'] : null;

        $options = [
            'convertTo'         => $convertTo,
            'moveRelatedDataTo' => $moveRelatedDataTo,
            'moveTagsTo'        => [$moveRelatedDataTo],
        ];

        $payload = ['lead_id' => $leadId, 'convert_to' => $convertTo];

        Connection::startTransaction();

        try {
            $mapping = ConvertService::getConversionMapping();

            $leadConvertService = new LeadConvertService([$leadId], $defaultOwnerId, $options, $mapping);

            $leadConvertService->convertToCompanies();

            $convertedContacts = $leadConvertService->convertToContacts();
            if (empty($convertedContacts) || !isset($convertedContacts[0]['id'])) {
                Connection::rollback();

                return ['status_code' => 400, 'payload' => $payload, 'response' => __('Failed to convert lead.', 'bit-pi')];
            }

            $leadConvertService->convertToDeals($convertedContacts);

            Lead::where('id', $leadId)->update(['is_converted' => 1]);

            Connection::commit();
        } catch (Throwable $th) {
            Connection::rollback();

            return ['status_code' => 400, 'payload' => $payload, 'response' => $th->getMessage()];
        }

        Hooks::doAction('bit_crm/leads_converted_to_contact', [$leadId]);

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData($convertedContacts)];
    }
}
