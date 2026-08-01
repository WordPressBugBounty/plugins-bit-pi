<?php

namespace BitApps\Pi\src\Integrations\BitCrm\Services;

use BitApps\Crm\Model\Deal;
use BitApps\Pi\Deps\BitApps\WPKit\Hooks\Hooks;
use BitApps\Pi\src\Integrations\BitCrm\BitCrmHelper;
use BitApps\Pi\src\Integrations\IntegrationHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class DealService extends BaseService
{
    public function createDeal()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\DealService')) {
            return $error;
        }

        $fields = $this->fields();
        $systemValues = $this->buildFieldValues(
            [
                'contact_id'  => $fields['contact_id'] ?? null,
                'company_id'  => $fields['company_id'] ?? null,
                'owner_id'    => $fields['owner_id'] ?? null,
                'stage'       => $fields['stage'] ?? null,
                'currency'    => $fields['currency'] ?? null,
                'type'        => $fields['type'] ?? null,
                'lead_source' => $fields['lead_source'] ?? null,
            ]
        );

        $error = IntegrationHelper::validateFieldMap(
            $systemValues,
            [
                'name'       => ['required', 'string'],
                'stage'      => ['required', 'string'],
                'contact_id' => ['required', 'integer'],
                'email'      => ['nullable', 'email'],
            ]
        );
        if (!empty($error)) {
            return $error;
        }

        $payload = [
            'systemDefinedFieldsValues' => $systemValues,
            'tagIds'                    => BitCrmHelper::toIntArray($fields['tag_ids'] ?? []),
            'lineItems'                 => $this->lineItemsFromRepeater(),
        ];

        $result = (new \BitApps\Crm\Services\DealService())->store($payload);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function updateDeal()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\DealService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['deal_id' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        $systemValues = $this->buildFieldValues(
            [
                'contact_id'  => $fields['contact_id'] ?? null,
                'company_id'  => $fields['company_id'] ?? null,
                'owner_id'    => $fields['owner_id'] ?? null,
                'stage'       => $fields['stage'] ?? null,
                'currency'    => $fields['currency'] ?? null,
                'type'        => $fields['type'] ?? null,
                'lead_source' => $fields['lead_source'] ?? null,
            ]
        );

        $payload = [
            'id'                        => (int) $fields['deal_id'],
            'systemDefinedFieldsValues' => $systemValues,
            'lineItems'                 => $this->lineItemsFromRepeater(),
        ];

        $result = (new \BitApps\Crm\Services\DealService())->update($payload);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function deleteDeal()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\DealService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['deal_id' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        $payload = ['ids' => [(int) $fields['deal_id']]];

        $result = (new \BitApps\Crm\Services\DealService())->trash($payload);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function getAllDeals()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Deal')) {
            return $error;
        }

        $deals = Deal::where('is_trash', 0)->get();

        return ['status_code' => 200, 'payload' => [], 'response' => $deals ? $deals->toArray() : []];
    }

    public function getDealById()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\DealService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['deal_id' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        $payload = ['id' => (int) $fields['deal_id']];

        $result = (new \BitApps\Crm\Services\DealService())->show($payload);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function getDealByEmail()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\DealService')) {
            return $error;
        }

        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Deal')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['email' => ['required', 'email']]);
        if (!empty($error)) {
            return $error;
        }

        $email = $fields['email'];
        $payload = ['email' => $email];

        $deal = BitCrmHelper::normalizeData(Deal::findOne(['email' => $email, 'is_trash' => 0]));

        if (empty($deal['id'])) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('No deal found with this email.', 'bit-pi')];
        }

        $result = (new \BitApps\Crm\Services\DealService())->show(['id' => (int) $deal['id']]);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function addTagToDeal()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\DealService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['deal_id' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        if (empty($fields['tag_ids']) && empty($fields['new_tags'])) {
            return ['status_code' => 400, 'payload' => [], 'response' => __('At least one existing or new tag must be provided.', 'bit-pi')];
        }

        $dealId = (int) $fields['deal_id'];
        $tagIds = BitCrmHelper::toIntArray($fields['tag_ids']);
        $newTags = array_values(array_filter((array) ($fields['new_tags'] ?? [])));

        $attached = (new \BitApps\Crm\Services\DealService())->storeAndAttachTags($dealId, $tagIds, $newTags);

        $payload = ['deal_id' => $dealId, 'tag_ids' => $tagIds];

        if (!empty($attached)) {
            Hooks::doAction('bit_crm/tags_attached_to_deals', $attached, [$dealId]);
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(Deal::findOne(['id' => $dealId]))];
    }

    public function removeTagFromDeal()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\DealService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['deal_id' => ['required'], 'tag_ids' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        $dealId = (int) $fields['deal_id'];
        $tagIds = BitCrmHelper::toIntArray($fields['tag_ids']);

        $detached = (new \BitApps\Crm\Services\DealService())->detachTags($dealId, $tagIds);

        $payload = ['deal_id' => $dealId, 'tag_ids' => $tagIds];

        if (!$detached) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('No matching tags were removed from the deal.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(Deal::findOne(['id' => $dealId]))];
    }

    public function updateDealStage()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Deal')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['deal_id' => ['required', 'integer'], 'stage' => ['required', 'string']]);
        if (!empty($error)) {
            return $error;
        }

        $dealId = (int) $fields['deal_id'];
        $stage = $fields['stage'];
        $payload = ['deal_id' => $dealId, 'stage' => $stage];

        $deal = Deal::findOne(['id' => $dealId, 'is_trash' => 0]);
        if (!$deal) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('Deal not found!', 'bit-pi')];
        }

        if (!$deal->update(['stage' => $stage, 'updated_by' => get_current_user_id()])) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('Failed to update deal stage.', 'bit-pi')];
        }

        Hooks::doAction('bit_crm/deal_stage_updated', $deal, $stage);

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData($deal)];
    }
}
