<?php

namespace BitApps\Pi\src\Integrations\BitCrm;

use BitApps\Pi\src\Integrations\HookRegisterInterface;

if (!defined('ABSPATH')) {
    exit;
}

class BitCrmHooks implements HookRegisterInterface
{
    public function register(): array
    {
        return array_merge(
            $this->leadHooks(),
            $this->contactHooks(),
            $this->companyHooks(),
            $this->dealHooks(),
            $this->productHooks(),
            $this->tagHooks(),
            $this->noteHooks(),
            $this->activityHooks(),
            $this->invoiceHooks(),
            $this->attachmentHooks(),
            $this->linkHooks(),
            $this->clientPortalHooks()
        );
    }

    /**
     * Fired by Bit CRM Pro, which owns the client portal, so these stay silent
     * on a Free-only install.
     */
    private function clientPortalHooks(): array
    {
        return [
            'portalAccessGranted' => ['hook' => 'bit_crm/client_portal_access_granted', 'callback' => [BitCrmTrigger::class, 'handlePortalAccessGranted'], 'accepted_args' => 2],
            'portalAccessRevoked' => ['hook' => 'bit_crm/client_portal_access_revoked', 'callback' => [BitCrmTrigger::class, 'handlePortalAccessRevoked'], 'accepted_args' => 2],
        ];
    }

    private function attachmentHooks(): array
    {
        return [
            'attachmentCreated' => ['hook' => 'bit_crm/attachment_created', 'callback' => [BitCrmTrigger::class, 'handleAttachmentCreated']],
            'attachmentDeleted' => ['hook' => 'bit_crm/attachment_deleted', 'callback' => [BitCrmTrigger::class, 'handleAttachmentDeleted']],
        ];
    }

    private function linkHooks(): array
    {
        return [
            'linkCreated' => ['hook' => 'bit_crm/link_created', 'callback' => [BitCrmTrigger::class, 'handleLinkCreated']],
            'linkUpdated' => ['hook' => 'bit_crm/link_updated', 'callback' => [BitCrmTrigger::class, 'handleLinkUpdated']],
            'linkDeleted' => ['hook' => 'bit_crm/link_deleted', 'callback' => [BitCrmTrigger::class, 'handleLinkDeleted']],
        ];
    }

    private function leadHooks(): array
    {
        return [
            'leadCreated'      => ['hook' => 'bit_crm/lead_created', 'callback' => [BitCrmTrigger::class, 'handleLeadCreated']],
            'leadUpdated'      => ['hook' => 'bit_crm/lead_updated', 'callback' => [BitCrmTrigger::class, 'handleLeadUpdated']],
            'leadTrashed'      => ['hook' => 'bit_crm/leads_trashed', 'callback' => [BitCrmTrigger::class, 'handleLeadTrashed']],
            'leadConverted'    => ['hook' => 'bit_crm/leads_converted_to_contact', 'callback' => [BitCrmTrigger::class, 'handleLeadConverted']],
            'leadTagAttached'  => ['hook' => 'bit_crm/tag_attached_to_lead', 'callback' => [BitCrmTrigger::class, 'handleLeadTagAttached'], 'accepted_args' => 2],
            'leadTagDetached'  => ['hook' => 'bit_crm/tag_detached_from_lead', 'callback' => [BitCrmTrigger::class, 'handleLeadTagDetached'], 'accepted_args' => 2],
            'leadTagsAttached' => ['hook' => 'bit_crm/tags_attached_to_leads', 'callback' => [BitCrmTrigger::class, 'handleLeadTagsAttached'], 'accepted_args' => 2],
            'leadTagsDetached' => ['hook' => 'bit_crm/tags_detached_from_leads', 'callback' => [BitCrmTrigger::class, 'handleLeadTagsDetached'], 'accepted_args' => 2],
        ];
    }

    private function contactHooks(): array
    {
        return [
            'contactCreated'      => ['hook' => 'bit_crm/contact_created', 'callback' => [BitCrmTrigger::class, 'handleContactCreated']],
            'contactUpdated'      => ['hook' => 'bit_crm/contact_updated', 'callback' => [BitCrmTrigger::class, 'handleContactUpdated']],
            'contactTrashed'      => ['hook' => 'bit_crm/contacts_trashed', 'callback' => [BitCrmTrigger::class, 'handleContactTrashed']],
            'contactTagAttached'  => ['hook' => 'bit_crm/tag_attached_to_contact', 'callback' => [BitCrmTrigger::class, 'handleContactTagAttached'], 'accepted_args' => 2],
            'contactTagDetached'  => ['hook' => 'bit_crm/tag_detached_from_contact', 'callback' => [BitCrmTrigger::class, 'handleContactTagDetached'], 'accepted_args' => 2],
            'contactTagsAttached' => ['hook' => 'bit_crm/tags_attached_to_contacts', 'callback' => [BitCrmTrigger::class, 'handleContactTagsAttached'], 'accepted_args' => 2],
            'contactTagsDetached' => ['hook' => 'bit_crm/tags_detached_from_contacts', 'callback' => [BitCrmTrigger::class, 'handleContactTagsDetached'], 'accepted_args' => 2],
        ];
    }

    private function companyHooks(): array
    {
        return [
            'companyCreated'      => ['hook' => 'bit_crm/company_created', 'callback' => [BitCrmTrigger::class, 'handleCompanyCreated']],
            'companyUpdated'      => ['hook' => 'bit_crm/company_updated', 'callback' => [BitCrmTrigger::class, 'handleCompanyUpdated']],
            'companyTrashed'      => ['hook' => 'bit_crm/companies_trashed', 'callback' => [BitCrmTrigger::class, 'handleCompanyTrashed']],
            'companyTagAttached'  => ['hook' => 'bit_crm/tag_attached_to_company', 'callback' => [BitCrmTrigger::class, 'handleCompanyTagAttached'], 'accepted_args' => 2],
            'companyTagDetached'  => ['hook' => 'bit_crm/tag_detached_from_company', 'callback' => [BitCrmTrigger::class, 'handleCompanyTagDetached'], 'accepted_args' => 2],
            'companyTagsAttached' => ['hook' => 'bit_crm/tags_attached_to_companies', 'callback' => [BitCrmTrigger::class, 'handleCompanyTagsAttached'], 'accepted_args' => 2],
            'companyTagsDetached' => ['hook' => 'bit_crm/tags_detached_from_companies', 'callback' => [BitCrmTrigger::class, 'handleCompanyTagsDetached'], 'accepted_args' => 2],
        ];
    }

    private function dealHooks(): array
    {
        return [
            'dealCreated'      => ['hook' => 'bit_crm/deal_created', 'callback' => [BitCrmTrigger::class, 'handleDealCreated']],
            'dealUpdated'      => ['hook' => 'bit_crm/deal_updated', 'callback' => [BitCrmTrigger::class, 'handleDealUpdated']],
            'dealTrashed'      => ['hook' => 'bit_crm/deals_trashed', 'callback' => [BitCrmTrigger::class, 'handleDealTrashed']],
            'dealStageUpdated' => ['hook' => 'bit_crm/deal_stage_updated', 'callback' => [BitCrmTrigger::class, 'handleDealStageUpdated'], 'accepted_args' => 2],
            'dealTagAttached'  => ['hook' => 'bit_crm/tag_attached_to_deal', 'callback' => [BitCrmTrigger::class, 'handleDealTagAttached'], 'accepted_args' => 2],
            'dealTagDetached'  => ['hook' => 'bit_crm/tag_detached_from_deal', 'callback' => [BitCrmTrigger::class, 'handleDealTagDetached'], 'accepted_args' => 2],
            'dealTagsAttached' => ['hook' => 'bit_crm/tags_attached_to_deals', 'callback' => [BitCrmTrigger::class, 'handleDealTagsAttached'], 'accepted_args' => 2],
            'dealTagsDetached' => ['hook' => 'bit_crm/tags_detached_from_deals', 'callback' => [BitCrmTrigger::class, 'handleDealTagsDetached'], 'accepted_args' => 2],
        ];
    }

    private function productHooks(): array
    {
        return [
            'productCreated'      => ['hook' => 'bit_crm/product_created', 'callback' => [BitCrmTrigger::class, 'handleProductCreated']],
            'productUpdated'      => ['hook' => 'bit_crm/product_updated', 'callback' => [BitCrmTrigger::class, 'handleProductUpdated']],
            'productTrashed'      => ['hook' => 'bit_crm/products_trashed', 'callback' => [BitCrmTrigger::class, 'handleProductTrashed']],
            'productTagAttached'  => ['hook' => 'bit_crm/tag_attached_to_product', 'callback' => [BitCrmTrigger::class, 'handleProductTagAttached'], 'accepted_args' => 2],
            'productTagDetached'  => ['hook' => 'bit_crm/tag_detached_from_product', 'callback' => [BitCrmTrigger::class, 'handleProductTagDetached'], 'accepted_args' => 2],
            'productTagsAttached' => ['hook' => 'bit_crm/tags_attached_to_products', 'callback' => [BitCrmTrigger::class, 'handleProductTagsAttached'], 'accepted_args' => 2],
            'productTagsDetached' => ['hook' => 'bit_crm/tags_detached_from_products', 'callback' => [BitCrmTrigger::class, 'handleProductTagsDetached'], 'accepted_args' => 2],
        ];
    }

    private function tagHooks(): array
    {
        return [
            'tagCreated' => ['hook' => 'bit_crm/tag_created', 'callback' => [BitCrmTrigger::class, 'handleTagCreated']],
            'tagUpdated' => ['hook' => 'bit_crm/tag_updated', 'callback' => [BitCrmTrigger::class, 'handleTagUpdated']],
            'tagDeleted' => ['hook' => 'bit_crm/tag_deleted', 'callback' => [BitCrmTrigger::class, 'handleTagDeleted']],
        ];
    }

    private function noteHooks(): array
    {
        return [
            'noteCreated' => ['hook' => 'bit_crm/note_created', 'callback' => [BitCrmTrigger::class, 'handleNoteCreated']],
            'noteUpdated' => ['hook' => 'bit_crm/note_updated', 'callback' => [BitCrmTrigger::class, 'handleNoteUpdated']],
            'noteDeleted' => ['hook' => 'bit_crm/note_deleted', 'callback' => [BitCrmTrigger::class, 'handleNoteDeleted']],
        ];
    }

    private function activityHooks(): array
    {
        return [
            'taskCreated'          => ['hook' => 'bit_crm/activity_created', 'callback' => [BitCrmTrigger::class, 'handleTaskCreated']],
            'taskUpdated'          => ['hook' => 'bit_crm/activity_updated', 'callback' => [BitCrmTrigger::class, 'handleTaskUpdated']],
            'taskStatusUpdated'    => ['hook' => 'bit_crm/activity_status_updated', 'callback' => [BitCrmTrigger::class, 'handleTaskStatusUpdated'], 'accepted_args' => 3],
            'meetingCreated'       => ['hook' => 'bit_crm/activity_created', 'callback' => [BitCrmTrigger::class, 'handleMeetingCreated']],
            'meetingUpdated'       => ['hook' => 'bit_crm/activity_updated', 'callback' => [BitCrmTrigger::class, 'handleMeetingUpdated']],
            'meetingStatusUpdated' => ['hook' => 'bit_crm/activity_status_updated', 'callback' => [BitCrmTrigger::class, 'handleMeetingStatusUpdated'], 'accepted_args' => 3],
            'callCreated'          => ['hook' => 'bit_crm/activity_created', 'callback' => [BitCrmTrigger::class, 'handleCallCreated']],
            'callUpdated'          => ['hook' => 'bit_crm/activity_updated', 'callback' => [BitCrmTrigger::class, 'handleCallUpdated']],
            'callStatusUpdated'    => ['hook' => 'bit_crm/activity_status_updated', 'callback' => [BitCrmTrigger::class, 'handleCallStatusUpdated'], 'accepted_args' => 3],
            'taskDeleted'          => ['hook' => 'bit_crm/activity_deleted', 'callback' => [BitCrmTrigger::class, 'handleTaskDeleted']],
            'meetingDeleted'       => ['hook' => 'bit_crm/activity_deleted', 'callback' => [BitCrmTrigger::class, 'handleMeetingDeleted']],
            'callDeleted'          => ['hook' => 'bit_crm/activity_deleted', 'callback' => [BitCrmTrigger::class, 'handleCallDeleted']],
        ];
    }

    private function invoiceHooks(): array
    {
        return [
            'invoiceCreated'       => ['hook' => 'bit_crm/invoice_created', 'callback' => [BitCrmTrigger::class, 'handleInvoiceCreated']],
            'invoiceUpdated'       => ['hook' => 'bit_crm/invoice_updated', 'callback' => [BitCrmTrigger::class, 'handleInvoiceUpdated']],
            'invoiceStatusUpdated' => ['hook' => 'bit_crm/invoice_status_updated', 'callback' => [BitCrmTrigger::class, 'handleInvoiceStatusUpdated']],
            'invoiceTrashed'       => ['hook' => 'bit_crm/invoices_trashed', 'callback' => [BitCrmTrigger::class, 'handleInvoiceTrashed']],
        ];
    }
}
