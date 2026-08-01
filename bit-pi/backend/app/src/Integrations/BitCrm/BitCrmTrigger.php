<?php

namespace BitApps\Pi\src\Integrations\BitCrm;

use BitApps\Crm\Factories\EntityFactory;
use BitApps\Pi\Services\FlowService;
use BitApps\Pi\src\Integrations\IntegrationHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class BitCrmTrigger
{
    public static function handleLeadCreated($lead): void
    {
        self::entityEvent('leadCreated', $lead);
    }

    public static function handleLeadUpdated($lead): void
    {
        self::entityEvent('leadUpdated', $lead);
    }

    public static function handleLeadTrashed($ids): void
    {
        self::entityTrashed('leadTrashed', $ids);
    }

    public static function handleLeadConverted($ids): void
    {
        self::execute('leadConverted', ['ids' => BitCrmHelper::toIntArray($ids)]);
    }

    public static function handleLeadTagAttached($tag, $leadId): void
    {
        self::tagSingleAttached('leadTagAttached', 'lead', $tag, $leadId);
    }

    public static function handleLeadTagDetached($tagId, $leadId): void
    {
        self::tagSingleDetached('leadTagDetached', 'lead', $tagId, $leadId);
    }

    public static function handleLeadTagsAttached($tagIds, $leadIds): void
    {
        self::tagsBulk('leadTagsAttached', 'lead', $tagIds, $leadIds);
    }

    public static function handleLeadTagsDetached($tagIds, $leadIds): void
    {
        self::tagsBulk('leadTagsDetached', 'lead', $tagIds, $leadIds);
    }

    public static function handleContactCreated($contact): void
    {
        self::entityEvent('contactCreated', $contact);
    }

    public static function handleContactUpdated($contact): void
    {
        self::entityEvent('contactUpdated', $contact);
    }

    public static function handleContactTrashed($ids): void
    {
        self::entityTrashed('contactTrashed', $ids);
    }

    public static function handleContactTagAttached($tag, $contactId): void
    {
        self::tagSingleAttached('contactTagAttached', 'contact', $tag, $contactId);
    }

    public static function handleContactTagDetached($tagId, $contactId): void
    {
        self::tagSingleDetached('contactTagDetached', 'contact', $tagId, $contactId);
    }

    public static function handleContactTagsAttached($tagIds, $contactIds): void
    {
        self::tagsBulk('contactTagsAttached', 'contact', $tagIds, $contactIds);
    }

    public static function handleContactTagsDetached($tagIds, $contactIds): void
    {
        self::tagsBulk('contactTagsDetached', 'contact', $tagIds, $contactIds);
    }

    public static function handleCompanyCreated($company): void
    {
        self::entityEvent('companyCreated', $company);
    }

    public static function handleCompanyUpdated($company): void
    {
        self::entityEvent('companyUpdated', $company);
    }

    public static function handleCompanyTrashed($ids): void
    {
        self::entityTrashed('companyTrashed', $ids);
    }

    public static function handleCompanyTagAttached($tag, $companyId): void
    {
        self::tagSingleAttached('companyTagAttached', 'company', $tag, $companyId);
    }

    public static function handleCompanyTagDetached($tagId, $companyId): void
    {
        self::tagSingleDetached('companyTagDetached', 'company', $tagId, $companyId);
    }

    public static function handleCompanyTagsAttached($tagIds, $companyIds): void
    {
        self::tagsBulk('companyTagsAttached', 'company', $tagIds, $companyIds);
    }

    public static function handleCompanyTagsDetached($tagIds, $companyIds): void
    {
        self::tagsBulk('companyTagsDetached', 'company', $tagIds, $companyIds);
    }

    public static function handleDealCreated($deal): void
    {
        self::entityEvent('dealCreated', $deal);
    }

    public static function handleDealUpdated($deal): void
    {
        self::entityEvent('dealUpdated', $deal);
    }

    public static function handleDealTrashed($ids): void
    {
        self::entityTrashed('dealTrashed', $ids);
    }

    public static function handleDealStageUpdated($deal, $stage): void
    {
        self::execute(
            'dealStageUpdated',
            [
                'entity' => BitCrmHelper::normalizeData($deal),
                'stage'  => $stage,
            ]
        );
    }

    public static function handleDealTagAttached($tag, $dealId): void
    {
        self::tagSingleAttached('dealTagAttached', 'deal', $tag, $dealId);
    }

    public static function handleDealTagDetached($tagId, $dealId): void
    {
        self::tagSingleDetached('dealTagDetached', 'deal', $tagId, $dealId);
    }

    public static function handleDealTagsAttached($tagIds, $dealIds): void
    {
        self::tagsBulk('dealTagsAttached', 'deal', $tagIds, $dealIds);
    }

    public static function handleDealTagsDetached($tagIds, $dealIds): void
    {
        self::tagsBulk('dealTagsDetached', 'deal', $tagIds, $dealIds);
    }

    public static function handleProductCreated($product): void
    {
        self::entityEvent('productCreated', $product);
    }

    public static function handleProductUpdated($product): void
    {
        self::entityEvent('productUpdated', $product);
    }

    public static function handleProductTrashed($ids): void
    {
        self::entityTrashed('productTrashed', $ids);
    }

    public static function handleProductTagAttached($tag, $productId): void
    {
        self::tagSingleAttached('productTagAttached', 'product', $tag, $productId);
    }

    public static function handleProductTagDetached($tagId, $productId): void
    {
        self::tagSingleDetached('productTagDetached', 'product', $tagId, $productId);
    }

    public static function handleProductTagsAttached($tagIds, $productIds): void
    {
        self::tagsBulk('productTagsAttached', 'product', $tagIds, $productIds);
    }

    public static function handleProductTagsDetached($tagIds, $productIds): void
    {
        self::tagsBulk('productTagsDetached', 'product', $tagIds, $productIds);
    }

    public static function handleTagCreated($tag): void
    {
        self::entityEvent('tagCreated', $tag);
    }

    public static function handleTagUpdated($tag): void
    {
        self::entityEvent('tagUpdated', $tag);
    }

    public static function handleTagDeleted($id): void
    {
        self::execute('tagDeleted', ['id' => (int) (\is_object($id) ? ($id->id ?? 0) : $id)]);
    }

    public static function handleNoteCreated($note): void
    {
        self::entityEvent('noteCreated', $note);
    }

    public static function handleNoteUpdated($note): void
    {
        self::entityEvent('noteUpdated', $note);
    }

    public static function handleNoteDeleted($id): void
    {
        self::execute('noteDeleted', ['id' => (int) (\is_object($id) ? ($id->id ?? 0) : $id)]);
    }

    public static function handleTaskCreated($activity): void
    {
        self::activityEvent('taskCreated', 'task', $activity);
    }

    public static function handleTaskUpdated($activity): void
    {
        self::activityEvent('taskUpdated', 'task', $activity);
    }

    public static function handleTaskStatusUpdated($activity, $newStatus = null, $oldStatus = null): void
    {
        self::activityStatusEvent('taskStatusUpdated', 'task', $activity, $newStatus, $oldStatus);
    }

    public static function handleMeetingCreated($activity): void
    {
        self::activityEvent('meetingCreated', 'meeting', $activity);
    }

    public static function handleMeetingUpdated($activity): void
    {
        self::activityEvent('meetingUpdated', 'meeting', $activity);
    }

    public static function handleMeetingStatusUpdated($activity, $newStatus = null, $oldStatus = null): void
    {
        self::activityStatusEvent('meetingStatusUpdated', 'meeting', $activity, $newStatus, $oldStatus);
    }

    public static function handleCallCreated($activity): void
    {
        self::activityEvent('callCreated', 'call', $activity);
    }

    public static function handleCallUpdated($activity): void
    {
        self::activityEvent('callUpdated', 'call', $activity);
    }

    public static function handleCallStatusUpdated($activity, $newStatus = null, $oldStatus = null): void
    {
        self::activityStatusEvent('callStatusUpdated', 'call', $activity, $newStatus, $oldStatus);
    }

    public static function handleTaskDeleted($activity): void
    {
        self::activityEvent('taskDeleted', 'task', $activity);
    }

    public static function handleMeetingDeleted($activity): void
    {
        self::activityEvent('meetingDeleted', 'meeting', $activity);
    }

    public static function handleCallDeleted($activity): void
    {
        self::activityEvent('callDeleted', 'call', $activity);
    }

    public static function handleInvoiceCreated($invoice): void
    {
        self::entityEvent('invoiceCreated', $invoice);
    }

    public static function handleInvoiceUpdated($invoice): void
    {
        self::entityEvent('invoiceUpdated', $invoice);
    }

    public static function handleInvoiceStatusUpdated($invoice): void
    {
        self::entityEvent('invoiceStatusUpdated', $invoice);
    }

    public static function handleInvoiceTrashed($ids): void
    {
        self::entityTrashed('invoiceTrashed', $ids);
    }

    public static function handleAttachmentCreated($attachment): void
    {
        self::entityEvent('attachmentCreated', $attachment);
    }

    public static function handleAttachmentDeleted($id): void
    {
        self::execute('attachmentDeleted', ['id' => (int) (\is_object($id) ? ($id->id ?? 0) : $id)]);
    }

    public static function handleLinkCreated($link): void
    {
        self::entityEvent('linkCreated', $link);
    }

    public static function handleLinkUpdated($link): void
    {
        self::entityEvent('linkUpdated', $link);
    }

    public static function handleLinkDeleted($id): void
    {
        self::execute('linkDeleted', ['id' => (int) (\is_object($id) ? ($id->id ?? 0) : $id)]);
    }

    public static function handlePortalAccessGranted($contact, $email = ''): void
    {
        self::portalEvent('portalAccessGranted', $contact, $email);
    }

    public static function handlePortalAccessRevoked($contact, $email = ''): void
    {
        self::portalEvent('portalAccessRevoked', $contact, $email);
    }

    private static function entityEvent(string $machineSlug, $entity): void
    {
        self::execute($machineSlug, BitCrmHelper::normalizeData($entity));
    }

    /**
     * Dispatch an activity create/update event, but only for the matching type.
     *
     * Bit CRM fires one hook for every activity, so a task flow must ignore
     * meeting and call events (and vice versa).
     *
     * @param mixed $activity
     */
    private static function activityEvent(string $machineSlug, string $type, $activity): void
    {
        $data = BitCrmHelper::normalizeData($activity);

        if (($data['type'] ?? '') !== $type) {
            return;
        }

        self::execute($machineSlug, $data);
    }

    private static function activityStatusEvent(string $machineSlug, string $type, $activity, $newStatus, $oldStatus): void
    {
        $data = BitCrmHelper::normalizeData($activity);

        if (($data['type'] ?? '') !== $type) {
            return;
        }

        self::execute(
            $machineSlug,
            [
                'entity'     => $data,
                'new_status' => $newStatus,
                'old_status' => $oldStatus,
            ]
        );
    }

    private static function entityTrashed(string $machineSlug, $ids): void
    {
        self::execute($machineSlug, ['ids' => BitCrmHelper::toIntArray($ids)]);
    }

    private static function tagSingleAttached(string $machineSlug, string $module, $tag, $entityId): void
    {
        $tagId = \is_object($tag) ? (int) ($tag->id ?? 0) : (int) $tag;

        self::execute(
            $machineSlug,
            [
                $module   => self::fetchEntity($module, (int) $entityId),
                'tag_ids' => [$tagId],
            ]
        );
    }

    private static function tagSingleDetached(string $machineSlug, string $module, $tagId, $entityId): void
    {
        self::execute(
            $machineSlug,
            [
                $module   => self::fetchEntity($module, (int) $entityId),
                'tag_ids' => [(int) $tagId],
            ]
        );
    }

    private static function tagsBulk(string $machineSlug, string $module, $tagIds, $entityIds): void
    {
        $tagIds = BitCrmHelper::toIntArray($tagIds);
        $entityIds = BitCrmHelper::toIntArray($entityIds);

        if (empty($tagIds) || empty($entityIds)) {
            return;
        }

        foreach ($entityIds as $entityId) {
            self::execute(
                $machineSlug,
                [
                    $module   => self::fetchEntity($module, (int) $entityId),
                    'tag_ids' => $tagIds,
                ]
            );
        }
    }

    /**
     * Dispatch a client portal event as the contact's fields plus the portal
     * email. Revoking can start from a WordPress user that has no CRM contact,
     * so the contact may be null and the email is the only certainty.
     *
     * @param mixed $contact
     * @param mixed $email
     */
    private static function portalEvent(string $machineSlug, $contact, $email): void
    {
        $data = empty($contact) ? [] : BitCrmHelper::normalizeData($contact);
        $data['email'] = (string) $email;

        self::execute($machineSlug, $data);
    }

    private static function fetchEntity(string $module, int $id): array
    {
        if (empty($id) || !class_exists('BitApps\Crm\Factories\EntityFactory')) {
            return ['id' => $id];
        }

        $data = EntityFactory::module($module)->findById($id);

        return \is_array($data) && !empty($data) ? $data : ['id' => $id];
    }

    private static function execute(string $machineSlug, array $data): void
    {
        $flows = FlowService::exists('bitCrm', $machineSlug);

        if (!$flows) {
            return;
        }

        IntegrationHelper::handleFlowForForm($flows, $data);
    }
}
