<?php

namespace BitApps\Pi\src\Integrations\SuiteDash\helpers;

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\Arr;
use BitApps\Pi\src\Integrations\SuiteDash\SuiteDashContact;
use InvalidArgumentException;

if (!\defined('ABSPATH')) {
    exit;
}


class SuiteDashActionHandler
{
    public static function getStructure($data)
    {
        foreach ($data as $key => $value) {
            $keys = explode(':', $key);

            if (\count($keys) > 1) {
                Arr::set($data, $keys, $value);
                unset($data[$key]);
            }
        }

        return $data;
    }

    public static function executeAction(
        string $machineSlug,
        SuiteDashContact $suiteDashContact,
        $contactId,
        $companyId,
        $choseContactId,
        $choseCompanyId,
        array $subscribeFields,
        array $contacts,
        array $companies,
        $pageNumber
    ) {
        switch ($machineSlug) {
            case 'createContact':
                return $suiteDashContact->createContact($contacts);

            case 'updateAContact':
                return $suiteDashContact->updateAContact($contactId, $contacts);

            case 'getContacts':
                return $choseContactId === 'getSpecificContact'
                    ? $suiteDashContact->getAContact($contactId)
                    : $suiteDashContact->listContacts($pageNumber);

            case 'createCompany':
                return $suiteDashContact->createCompany($companies);

            case 'updateACompany':
                return $suiteDashContact->updateACompany($companyId, $companies);

            case 'getCompanies':
                return $choseCompanyId === 'getSpecificCompany'
                    ? $suiteDashContact->getACompany($companyId)
                    : $suiteDashContact->listCompanies($pageNumber);

            case 'subscribeMarketing':
                return $suiteDashContact->subscribeMarketing($subscribeFields);

            default:
                throw new InvalidArgumentException("Invalid machine slug: {$machineSlug}");
        }
    }
}
