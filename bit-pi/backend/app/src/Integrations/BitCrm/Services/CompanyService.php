<?php

namespace BitApps\Pi\src\Integrations\BitCrm\Services;

use BitApps\Crm\Model\Company;
use BitApps\Pi\Deps\BitApps\WPKit\Hooks\Hooks;
use BitApps\Pi\src\Integrations\BitCrm\BitCrmHelper;
use BitApps\Pi\src\Integrations\IntegrationHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class CompanyService extends BaseService
{
    public function createCompany()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\CompanyService')) {
            return $error;
        }

        $fields = $this->fields();
        $systemValues = $this->buildFieldValues(
            [
                'parent_id' => $fields['parent_id'] ?? null,
                'owner_id'  => $fields['owner_id'] ?? null,
                'currency'  => $fields['currency'] ?? null,
            ]
        );

        $payload = [
            'systemDefinedFieldsValues' => $systemValues,
            'tagIds'                    => BitCrmHelper::toIntArray($fields['tag_ids'] ?? []),
        ];

        $result = (new \BitApps\Crm\Services\CompanyService())->store($payload);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function updateCompany()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\CompanyService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['company_id' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        $systemValues = $this->buildFieldValues(
            [
                'parent_id' => $fields['parent_id'] ?? null,
                'owner_id'  => $fields['owner_id'] ?? null,
                'currency'  => $fields['currency'] ?? null,
            ]
        );

        $payload = [
            'id'                        => (int) $fields['company_id'],
            'systemDefinedFieldsValues' => $systemValues,
        ];

        $result = (new \BitApps\Crm\Services\CompanyService())->update($payload);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function deleteCompany()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\CompanyService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['company_id' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        $payload = ['ids' => [(int) $fields['company_id']]];

        $result = (new \BitApps\Crm\Services\CompanyService())->trash($payload);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function getAllCompanies()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Company')) {
            return $error;
        }

        $companies = Company::where('is_trash', 0)->get();

        return ['status_code' => 200, 'payload' => [], 'response' => $companies ? $companies->toArray() : []];
    }

    public function getCompanyById()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\CompanyService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['company_id' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        $payload = ['id' => (int) $fields['company_id']];

        $result = (new \BitApps\Crm\Services\CompanyService())->show($payload);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function getCompanyByName()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\CompanyService')) {
            return $error;
        }

        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Company')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['name' => ['required', 'string']]);
        if (!empty($error)) {
            return $error;
        }

        // Companies carry no email, so the name is their only natural key.
        $name = $fields['name'];
        $payload = ['name' => $name];

        $company = BitCrmHelper::normalizeData(Company::findOne(['name' => $name, 'is_trash' => 0]));

        if (empty($company['id'])) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('No company found with this name.', 'bit-pi')];
        }

        $result = (new \BitApps\Crm\Services\CompanyService())->show(['id' => (int) $company['id']]);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => (\is_array($result) ? ($result['errors'] ?? null) : null) ?? __('Bit CRM operation failed.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(\is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result)];
    }

    public function addTagToCompany()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\CompanyService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['company_id' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        if (empty($fields['tag_ids']) && empty($fields['new_tags'])) {
            return ['status_code' => 400, 'payload' => [], 'response' => __('At least one existing or new tag must be provided.', 'bit-pi')];
        }

        $companyId = (int) $fields['company_id'];
        $tagIds = BitCrmHelper::toIntArray($fields['tag_ids']);
        $newTags = array_values(array_filter((array) ($fields['new_tags'] ?? [])));

        $attached = (new \BitApps\Crm\Services\CompanyService())->storeAndAttachTags($companyId, $tagIds, $newTags);

        $payload = ['company_id' => $companyId, 'tag_ids' => $tagIds];

        if (!empty($attached)) {
            Hooks::doAction('bit_crm/tags_attached_to_companies', $attached, [$companyId]);
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(Company::findOne(['id' => $companyId]))];
    }

    public function removeTagFromCompany()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\CompanyService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['company_id' => ['required'], 'tag_ids' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        $companyId = (int) $fields['company_id'];
        $tagIds = BitCrmHelper::toIntArray($fields['tag_ids']);

        $detached = (new \BitApps\Crm\Services\CompanyService())->detachTags($companyId, $tagIds);

        $payload = ['company_id' => $companyId, 'tag_ids' => $tagIds];

        if (!$detached) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('No matching tags were removed from the company.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData(Company::findOne(['id' => $companyId]))];
    }
}
