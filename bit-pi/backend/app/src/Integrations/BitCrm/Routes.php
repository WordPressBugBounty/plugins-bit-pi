<?php

if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Router\Route;
use BitApps\Pi\src\Integrations\BitCrm\BitCrmHelper;

Route::group(
    function () {
        Route::get('bitcrm/currencies', [BitCrmHelper::class, 'getCurrencies']);
        Route::get('bitcrm/users', [BitCrmHelper::class, 'getUsers']);
        Route::get('bitcrm/deal-stages', [BitCrmHelper::class, 'getDealStages']);
        Route::get('bitcrm/invoice-terms', [BitCrmHelper::class, 'getInvoiceTerms']);

        Route::get('bitcrm/leads', [BitCrmHelper::class, 'getLeads']);
        Route::get('bitcrm/contacts', [BitCrmHelper::class, 'getContacts']);
        Route::get('bitcrm/companies', [BitCrmHelper::class, 'getCompanies']);
        Route::get('bitcrm/deals', [BitCrmHelper::class, 'getDeals']);
        Route::get('bitcrm/products', [BitCrmHelper::class, 'getProducts']);

        Route::get('bitcrm/notes', [BitCrmHelper::class, 'getNotes']);
        Route::get('bitcrm/attachments', [BitCrmHelper::class, 'getAttachments']);
        Route::get('bitcrm/links', [BitCrmHelper::class, 'getLinks']);
        Route::get('bitcrm/tasks', [BitCrmHelper::class, 'getTasks']);
        Route::get('bitcrm/meetings', [BitCrmHelper::class, 'getMeetings']);
        Route::get('bitcrm/calls', [BitCrmHelper::class, 'getCalls']);
        Route::get('bitcrm/invoices', [BitCrmHelper::class, 'getInvoices']);
        Route::post('bitcrm/entities', [BitCrmHelper::class, 'getEntities']);

        Route::get('bitcrm/tags', [BitCrmHelper::class, 'getTags']);
        Route::get('bitcrm/lead-tags', [BitCrmHelper::class, 'getLeadTags']);
        Route::get('bitcrm/contact-tags', [BitCrmHelper::class, 'getContactTags']);
        Route::get('bitcrm/company-tags', [BitCrmHelper::class, 'getCompanyTags']);
        Route::get('bitcrm/deal-tags', [BitCrmHelper::class, 'getDealTags']);
        Route::get('bitcrm/product-tags', [BitCrmHelper::class, 'getProductTags']);
    }
)->middleware('nonce:admin');
