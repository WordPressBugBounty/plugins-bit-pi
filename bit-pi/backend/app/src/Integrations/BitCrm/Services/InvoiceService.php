<?php

namespace BitApps\Pi\src\Integrations\BitCrm\Services;

use BitApps\Crm\Deps\BitApps\WPDatabase\Connection;
use BitApps\Crm\Model\Deal;
use BitApps\Crm\Model\Invoice;
use BitApps\Crm\Model\LineItem;
use BitApps\Crm\Model\Trash;
use BitApps\Crm\Services\LineItemService;
use BitApps\Pi\Deps\BitApps\WPKit\Hooks\Hooks;
use BitApps\Pi\src\Integrations\BitCrm\BitCrmHelper;
use BitApps\Pi\src\Integrations\IntegrationHelper;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

final class InvoiceService extends BaseService
{
    public function createInvoice()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Invoice')) {
            return $error;
        }

        $fields = $this->fields();
        $lineItems = $this->lineItemsFromRepeater();

        $error = IntegrationHelper::validateFieldMap(
            array_merge($fields, ['line_items' => $lineItems]),
            [
                'invoice_date'          => ['required', 'string'],
                'deal_id'               => ['required', 'integer'],
                'term_key'              => ['required', 'string'],
                'due_date'              => ['required', 'string'],
                'tax_option'            => ['required', 'string'],
                'currency'              => ['required', 'string'],
                'invoice_prefix'        => ['required', 'string'],
                'line_items'            => ['required'],
                'gross_discount_amount' => ['nullable', 'numeric'],
            ]
        );
        if (!empty($error)) {
            return $error;
        }

        $currency = $fields['currency'];
        $taxOption = $fields['tax_option'];

        $invoiceData = [
            'invoice_date'   => $fields['invoice_date'],
            'entity_id'      => (int) $fields['deal_id'],
            'module'         => Deal::MODULE_NAME,
            'term_key'       => $fields['term_key'],
            'due_date'       => $fields['due_date'],
            'tax_option'     => $taxOption,
            'currency'       => $currency,
            'invoice_prefix' => $fields['invoice_prefix'],
            'created_by'     => get_current_user_id(),
        ];

        if (isset($fields['gross_discount_amount']) && $fields['gross_discount_amount'] !== '') {
            $invoiceData['gross_discount_amount'] = $fields['gross_discount_amount'];
            $invoiceData['gross_discount_type'] = $fields['gross_discount_type'] ?? 'percentage';
        }

        $payload = array_merge($invoiceData, ['line_items' => $lineItems]);

        Connection::startTransaction();

        try {
            $storedInvoice = Invoice::insert($invoiceData);

            if (!$storedInvoice) {
                Connection::rollback();

                return ['status_code' => 400, 'payload' => $payload, 'response' => __('Failed to create invoice.', 'bit-pi')];
            }

            $lineItemService = new LineItemService($storedInvoice->id, Invoice::MODULE_NAME);
            $lineItemService->syncLineItems($lineItems, $currency, $taxOption);

            Connection::commit();
        } catch (Throwable $th) {
            Connection::rollback();

            return ['status_code' => 400, 'payload' => $payload, 'response' => $th->getMessage()];
        }

        Hooks::doAction('bit_crm/invoice_created', $storedInvoice);

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData($storedInvoice)];
    }

    public function updateInvoice()
    {
        [$invoice, $payload, $error] = $this->resolveInvoice();
        if ($error !== null) {
            return $error;
        }

        // Bit CRM locks paid invoices; respect that instead of writing behind its back.
        if ($invoice->status === Invoice::STATUS_PAID) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('Cannot update a paid invoice.', 'bit-pi')];
        }

        $fields = $this->fields();
        $lineItems = $this->lineItemsFromRepeater();

        $updateData = ['updated_by' => get_current_user_id()];

        foreach (['invoice_date', 'due_date', 'term_key', 'tax_option', 'currency', 'invoice_prefix'] as $field) {
            if (isset($fields[$field]) && $fields[$field] !== '') {
                $updateData[$field] = $fields[$field];
            }
        }

        if (!empty($fields['deal_id'])) {
            $updateData['entity_id'] = (int) $fields['deal_id'];
            $updateData['module'] = Deal::MODULE_NAME;
        }

        if (isset($fields['gross_discount_amount']) && $fields['gross_discount_amount'] !== '') {
            $updateData['gross_discount_amount'] = $fields['gross_discount_amount'];
            $updateData['gross_discount_type'] = $fields['gross_discount_type'] ?? 'percentage';
        }

        if (!empty($fields['status'])) {
            if (!Invoice::canTransitionStatus($invoice->status, $fields['status'])) {
                return ['status_code' => 400, 'payload' => $payload, 'response' => __('Invalid invoice status transition.', 'bit-pi')];
            }

            $updateData['status'] = $fields['status'];

            if ($fields['status'] === Invoice::STATUS_PAID) {
                $updateData['paid_at'] = current_time('mysql');
            }
        }

        $payload = array_merge($payload, $updateData, ['line_items' => $lineItems]);

        Connection::startTransaction();

        try {
            $invoice->update($updateData);

            // Only touch line items when the flow actually supplied some, so an
            // update of, say, the due date alone does not wipe the invoice.
            if (!empty($lineItems)) {
                $currency = $updateData['currency'] ?? self::dealCurrency($invoice);

                if (empty($currency)) {
                    Connection::rollback();

                    return ['status_code' => 400, 'payload' => $payload, 'response' => __('A currency is required to update the invoice line items.', 'bit-pi')];
                }

                $lineItemService = new LineItemService($invoice->id, Invoice::MODULE_NAME);
                $lineItemService->syncLineItems(
                    $lineItems,
                    $currency,
                    $updateData['tax_option'] ?? $invoice->tax_option
                );
            }

            Connection::commit();
        } catch (Throwable $th) {
            Connection::rollback();

            return ['status_code' => 400, 'payload' => $payload, 'response' => $th->getMessage()];
        }

        Hooks::doAction('bit_crm/invoice_updated', $invoice);

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData($invoice)];
    }

    public function updateInvoiceStatus()
    {
        [$invoice, $payload, $error] = $this->resolveInvoice();
        if ($error !== null) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['status' => ['required', 'string']]);
        if (!empty($error)) {
            return $error;
        }

        $status = $fields['status'];
        $payload = array_merge($payload, ['status' => $status]);

        if ($invoice->status === Invoice::STATUS_PAID) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('Cannot change the status of a paid invoice.', 'bit-pi')];
        }

        if (!Invoice::canTransitionStatus($invoice->status, $status)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('Invalid invoice status transition.', 'bit-pi')];
        }

        $updateData = ['status' => $status, 'updated_by' => get_current_user_id()];

        if ($status === Invoice::STATUS_PAID) {
            $updateData['paid_at'] = current_time('mysql');
        }

        if ($status === Invoice::STATUS_SENT) {
            $updateData['sent_at'] = current_time('mysql');
        }

        try {
            $invoice->update($updateData);
        } catch (Throwable $th) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => $th->getMessage()];
        }

        Hooks::doAction('bit_crm/invoice_status_updated', $invoice);

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData($invoice)];
    }

    public function deleteInvoice()
    {
        [$invoice, $payload, $error] = $this->resolveInvoice();
        if ($error !== null) {
            return $error;
        }

        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Trash')) {
            return $error;
        }

        $invoiceId = $payload['id'];
        $trashedInvoice = BitCrmHelper::normalizeData($invoice);

        Connection::startTransaction();

        try {
            // Bit CRM soft-deletes invoices: flag the row and mirror it into the trash bin.
            Invoice::whereIn('id', [$invoiceId])->update(['is_trash' => true]);

            Trash::insert(
                [
                    [
                        'entity_id'  => $invoiceId,
                        'module'     => Invoice::MODULE_NAME,
                        'created_by' => get_current_user_id(),
                        'full_name'  => ($trashedInvoice['invoice_prefix'] ?? '') . '-' . $invoiceId,
                    ],
                ]
            );

            Connection::commit();
        } catch (Throwable $th) {
            Connection::rollback();

            return ['status_code' => 400, 'payload' => $payload, 'response' => $th->getMessage()];
        }

        Hooks::doAction('bit_crm/invoices_trashed', [$invoiceId]);

        return ['status_code' => 200, 'payload' => $payload, 'response' => $trashedInvoice];
    }

    public function getAllInvoices()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Invoice')) {
            return $error;
        }

        $invoices = Invoice::where('is_trash', 0)->get();

        return ['status_code' => 200, 'payload' => [], 'response' => $invoices ? $invoices->toArray() : []];
    }

    public function getInvoicesByDeal()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Invoice')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['deal_id' => ['required', 'integer']]);
        if (!empty($error)) {
            return $error;
        }

        $dealId = (int) $fields['deal_id'];
        $payload = ['deal_id' => $dealId];

        $invoices = Invoice::where('entity_id', $dealId)
            ->where('module', Deal::MODULE_NAME)
            ->where('is_trash', 0)
            ->get();

        return ['status_code' => 200, 'payload' => $payload, 'response' => $invoices ? $invoices->toArray() : []];
    }

    public function getLineItemsByInvoice()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\LineItem')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['invoice_id' => ['required', 'integer']]);
        if (!empty($error)) {
            return $error;
        }

        $invoiceId = (int) $fields['invoice_id'];
        $payload = ['invoice_id' => $invoiceId];

        $lineItems = LineItem::where('entity_id', $invoiceId)
            ->where('module', Invoice::MODULE_NAME)
            ->get();

        return ['status_code' => 200, 'payload' => $payload, 'response' => $lineItems ? $lineItems->toArray() : []];
    }

    public function getInvoiceById()
    {
        [$invoice, $payload, $error] = $this->resolveInvoice();
        if ($error !== null) {
            return $error;
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData($invoice)];
    }

    /**
     * The currency of the deal an invoice belongs to.
     *
     * Invoices carry no currency column of their own — Bit CRM takes it from the
     * request on every write and otherwise reads it off the deal — so this is the
     * only place to recover it when a flow updates line items without naming one.
     *
     * @param mixed $invoice
     *
     * @return string
     */
    private static function dealCurrency($invoice)
    {
        if (!class_exists('BitApps\Crm\Model\Deal')) {
            return '';
        }

        $entityId = (int) ($invoice->entity_id ?? 0);
        if (empty($entityId) || $invoice->module !== Deal::MODULE_NAME) {
            return '';
        }

        $deal = BitCrmHelper::normalizeData(Deal::findOne(['id' => $entityId]));

        return (string) ($deal['currency'] ?? '');
    }

    private function resolveInvoice()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Invoice')) {
            return [null, [], $error];
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['invoice_id' => ['required', 'integer']]);
        if (!empty($error)) {
            return [null, [], $error];
        }

        $invoiceId = (int) $fields['invoice_id'];
        $payload = ['id' => $invoiceId];

        $invoice = Invoice::findOne(['id' => $invoiceId, 'is_trash' => 0]);

        if (empty($invoice)) {
            return [
                null,
                $payload,
                ['status_code' => 400, 'payload' => $payload, 'response' => __('Invoice not found.', 'bit-pi')],
            ];
        }

        return [$invoice, $payload, null];
    }
}
