<?php

namespace BitApps\Pi\src\Integrations\BitCrm;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Crm\Model\Activity;
use BitApps\Crm\Model\Attachment;
use BitApps\Crm\Model\Invoice;
use BitApps\Crm\Model\Link;
use BitApps\Crm\Model\Note;
use BitApps\Crm\Model\Tag;
use BitApps\Crm\Services\CompanyService;
use BitApps\Crm\Services\ContactService;
use BitApps\Crm\Services\CurrencyService;
use BitApps\Crm\Services\DealService;
use BitApps\Crm\Services\DealStageService;
use BitApps\Crm\Services\InvoiceTermService;
use BitApps\Crm\Services\LeadService;
use BitApps\Crm\Services\UserService;
use BitApps\CrmPro\Services\ProductService;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\Helpers\Utility;

final class BitCrmHelper
{
    private static $isActive;

    private static $isProActive;

    public static function isActive(): bool
    {
        if (self::$isActive === null) {
            self::$isActive = class_exists('BitApps\Crm\Config');
        }

        return self::$isActive;
    }

    public static function isProActive(): bool
    {
        if (self::$isProActive === null) {
            self::$isProActive = class_exists('BitApps\CrmPro\Services\ProductService');
        }

        return self::$isProActive;
    }

    public static function getCurrencies()
    {
        if ($inactive = self::guardActive('BitApps\Crm\Services\CurrencyService')) {
            return $inactive;
        }

        return Response::success(self::toOptions((new CurrencyService())->getOtherCurrenciesAsOptions()));
    }

    public static function getUsers()
    {
        if ($inactive = self::guardActive('BitApps\Crm\Services\UserService')) {
            return $inactive;
        }

        return Response::success(self::toOptions((new UserService())->getUsersAsOptions()));
    }

    public static function getDealStages()
    {
        if ($inactive = self::guardActive('BitApps\Crm\Services\DealStageService')) {
            return $inactive;
        }

        $stages = (new DealStageService())->getStagesAsOptions(DealStageService::STATUS_ACTIVE);

        return Response::success(self::toOptions($stages));
    }

    public static function getInvoiceTerms()
    {
        if ($inactive = self::guardActive('BitApps\Crm\Services\InvoiceTermService')) {
            return $inactive;
        }

        return Response::success(self::toOptions((new InvoiceTermService())->getTermsAsOptions()));
    }

    public static function getLeads()
    {
        if ($inactive = self::guardActive('BitApps\Crm\Services\LeadService')) {
            return $inactive;
        }

        return Response::success(self::toOptions((new LeadService())->getEntitiesAsOptions()));
    }

    public static function getContacts()
    {
        if ($inactive = self::guardActive('BitApps\Crm\Services\ContactService')) {
            return $inactive;
        }

        return Response::success(self::toOptions((new ContactService())->getEntitiesAsOptions()));
    }

    public static function getCompanies()
    {
        if ($inactive = self::guardActive('BitApps\Crm\Services\CompanyService')) {
            return $inactive;
        }

        return Response::success(self::toOptions((new CompanyService())->getEntitiesAsOptions()));
    }

    public static function getDeals()
    {
        if ($inactive = self::guardActive('BitApps\Crm\Services\DealService')) {
            return $inactive;
        }

        return Response::success(self::toOptions((new DealService())->getEntitiesAsOptions()));
    }

    public static function getProducts()
    {
        if (!self::isProActive()) {
            return Response::error(__('Bit CRM Pro (Product module) is not installed or activated', 'bit-pi'));
        }

        return Response::success(self::toOptions((new ProductService())->getEntitiesAsOptions()));
    }

    public static function getEntities(Request $request)
    {
        if ($inactive = self::guardActive()) {
            return $inactive;
        }

        $module = sanitize_text_field((string) $request->module);

        $serviceByModule = [
            'lead'    => 'BitApps\Crm\Services\LeadService',
            'contact' => 'BitApps\Crm\Services\ContactService',
            'company' => 'BitApps\Crm\Services\CompanyService',
            'deal'    => 'BitApps\Crm\Services\DealService',
        ];

        if (!isset($serviceByModule[$module])) {
            return Response::success([]);
        }

        $serviceClass = $serviceByModule[$module];

        if (!class_exists($serviceClass)) {
            return Response::success([]);
        }

        return Response::success(self::toOptions((new $serviceClass())->getEntitiesAsOptions()));
    }

    public static function getAttachments()
    {
        if ($inactive = self::guardActive('BitApps\Crm\Model\Attachment')) {
            return $inactive;
        }

        return Response::success(self::recordOptions(Attachment::all(), 'file_name', __('Attachment', 'bit-pi')));
    }

    public static function getLinks()
    {
        if ($inactive = self::guardActive('BitApps\Crm\Model\Link')) {
            return $inactive;
        }

        return Response::success(self::recordOptions(Link::all(), 'title', __('Link', 'bit-pi')));
    }

    public static function getNotes()
    {
        if ($inactive = self::guardActive('BitApps\Crm\Model\Note')) {
            return $inactive;
        }

        return Response::success(self::recordOptions(Note::all(), 'title', __('Note', 'bit-pi')));
    }

    public static function getTasks()
    {
        return self::activityOptionsResponse('task');
    }

    public static function getMeetings()
    {
        return self::activityOptionsResponse('meeting');
    }

    public static function getCalls()
    {
        return self::activityOptionsResponse('call');
    }

    public static function getInvoices()
    {
        if ($inactive = self::guardActive('BitApps\Crm\Model\Invoice')) {
            return $inactive;
        }

        $invoices = Invoice::where('is_trash', 0)->get();

        if (empty($invoices)) {
            return Response::success([]);
        }

        $options = [];
        foreach ($invoices->toArray() as $invoice) {
            $id = $invoice['id'] ?? '';
            $options[] = [
                'value' => $id,
                'label' => trim(($invoice['invoice_prefix'] ?? '') . '-' . $id, '-'),
            ];
        }

        return Response::success($options);
    }

    /**
     * Every tag across all modules, labelled with the module it belongs to.
     *
     * Bit CRM only exposes tags per module, but tag-level actions (update,
     * delete, fetch) are module-agnostic and need the full list.
     */
    public static function getTags()
    {
        if ($inactive = self::guardActive('BitApps\Crm\Model\Tag')) {
            return $inactive;
        }

        $tags = Tag::all();

        if (empty($tags)) {
            return Response::success([]);
        }

        $options = [];
        foreach ($tags->toArray() as $tag) {
            $options[] = [
                'value' => $tag['id'] ?? '',
                'label' => \sprintf('%s (%s)', $tag['title'] ?? '', $tag['module'] ?? ''),
            ];
        }

        return Response::success($options);
    }

    public static function getLeadTags()
    {
        return self::tagOptionsResponse('lead');
    }

    public static function getContactTags()
    {
        return self::tagOptionsResponse('contact');
    }

    public static function getCompanyTags()
    {
        return self::tagOptionsResponse('company');
    }

    public static function getDealTags()
    {
        return self::tagOptionsResponse('deal');
    }

    public static function getProductTags()
    {
        return self::tagOptionsResponse('product');
    }

    public static function normalizeData($data): array
    {
        if (empty($data)) {
            return [];
        }

        if (\is_object($data) && method_exists($data, 'getAttributes')) {
            return $data->getAttributes();
        }

        if (\is_object($data) && method_exists($data, 'toArray')) {
            return $data->toArray();
        }

        if (\is_array($data)) {
            return $data;
        }

        return (array) Utility::jsonEncodeDecode($data);
    }

    /**
     * Guard a specific Bit CRM class before it is used.
     *
     * `isActive()` only proves the CRM plugin is loaded; a given service/model
     * class could still be absent on a mismatched CRM version. Returns a ready
     * 400 action envelope when the class is missing, or null when it exists.
     *
     * @return null|array{status_code: int, payload: array, response: string}
     */
    public static function validateClassExists(string $class): ?array
    {
        if (class_exists($class)) {
            return null;
        }

        return [
            'status_code' => 400,
            'payload'     => [],
            'response'    => \sprintf(
                // translators: %s: fully-qualified Bit CRM class name
                __('Required Bit CRM component "%s" is not available. Please update Bit CRM.', 'bit-pi'),
                $class
            ),
        ];
    }

    public static function toIntArray($value): array
    {
        return array_values(
            array_filter(
                array_map('intval', (array) $value),
                static function ($id) {
                    return $id > 0;
                }
            )
        );
    }

    public static function tagOptions(string $module): array
    {
        if (!self::isActive()) {
            return [];
        }

        $tags = Tag::where('module', $module)->get();

        if (empty($tags)) {
            return [];
        }

        $options = [];
        foreach ($tags->toArray() as $tag) {
            $options[] = [
                'value' => $tag['id'] ?? '',
                'label' => $tag['title'] ?? '',
            ];
        }

        return $options;
    }

    private static function activityOptionsResponse(string $type)
    {
        if ($inactive = self::guardActive('BitApps\Crm\Model\Activity')) {
            return $inactive;
        }

        return Response::success(
            self::recordOptions(Activity::where('type', $type)->get(), 'title', ucfirst($type))
        );
    }

    private static function recordOptions($records, string $labelColumn, string $fallbackLabel): array
    {
        if (empty($records)) {
            return [];
        }

        $options = [];
        foreach ($records->toArray() as $record) {
            $id = $record['id'] ?? '';
            $label = $record[$labelColumn] ?? '';

            $options[] = [
                'value' => $id,
                'label' => $label === '' ? \sprintf('%s #%s', $fallbackLabel, $id) : $label,
            ];
        }

        return $options;
    }

    private static function tagOptionsResponse(string $module)
    {
        if ($inactive = self::guardActive('BitApps\Crm\Model\Tag')) {
            return $inactive;
        }

        return Response::success(self::tagOptions($module));
    }

    private static function guardActive(?string $class = null)
    {
        if (!self::isActive()) {
            return Response::error(__('Bit CRM is not installed or activated', 'bit-pi'));
        }

        if ($class !== null && !class_exists($class)) {
            return Response::error(
                \sprintf(
                    // translators: %s: fully-qualified Bit CRM class name
                    __('Required Bit CRM component "%s" is not available. Please update Bit CRM.', 'bit-pi'),
                    $class
                )
            );
        }
    }

    private static function toOptions($options): array
    {
        if (empty($options) || !\is_array($options)) {
            return [];
        }

        $normalized = [];
        foreach ($options as $option) {
            $option = (array) $option;
            if (!isset($option['value'])) {
                continue;
            }

            $normalized[] = [
                'value' => $option['value'],
                'label' => $option['label'] ?? $option['value'],
            ];
        }

        return $normalized;
    }
}
