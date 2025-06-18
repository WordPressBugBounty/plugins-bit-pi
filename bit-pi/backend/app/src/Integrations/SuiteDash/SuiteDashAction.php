<?php

namespace BitApps\Pi\src\Integrations\SuiteDash;

use BitApps\Pi\Helpers\MixInputHandler;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Integrations\SuiteDash\helpers\SuiteDashActionHandler;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!\defined('ABSPATH')) {
    exit;
}


class SuiteDashAction implements ActionInterface
{
    private const BASE_URL = 'https://app.suitedash.com/secure-api';

    private NodeInfoProvider $nodeInfoProvider;

    private SuiteDashContact $suiteDashContact;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeSuiteDashAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeSuiteDashAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();
        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');
        $contacts = $this->nodeInfoProvider->getFieldMapRepeaters('contacts-list.value', false, true, 'suiteDashField');
        $contactId = $this->nodeInfoProvider->getFieldMapConfigs('contact-id.value');
        $companies = $this->nodeInfoProvider->getFieldMapRepeaters('companies-list.value', false, true, 'suiteDashField');
        $companyId = $this->nodeInfoProvider->getFieldMapConfigs('company-id.value');
        $choseContactId = $this->nodeInfoProvider->getFieldMapConfigs('chose-contact-id.value');
        $choseCompanyId = $this->nodeInfoProvider->getFieldMapConfigs('chose-company-id.value');
        $subscribeFields = $this->nodeInfoProvider->getFieldMapRepeaters('subscribes-list.value', false, false);
        $role = $this->nodeInfoProvider->getFieldMapConfigs('role-id.value');
        $pageNumberMixValue = $this->nodeInfoProvider->getFieldMapConfigs('page-number.value');
        $pageNumber = MixInputHandler::replaceMixTagValue($pageNumberMixValue);
        $checkPrimaryContact = $this->nodeInfoProvider->getFieldMapConfigs('check-contact.value');

        $contacts = SuiteDashActionHandler::getStructure($contacts);
        $companies = SuiteDashActionHandler::getStructure($companies);

        $contacts['role'] = $role;
        $companies['role'] = $role;
        $companies['primaryContact']['create_primary_contact_if_not_exists'] = filter_var($checkPrimaryContact, FILTER_VALIDATE_BOOLEAN);

        foreach ($subscribeFields as &$item) {
            $item['audiences'] = array_map('intval', explode(',', $item['audiences']));
        }

        $tokenAuthorization = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::API_KEY,
            $connectionId
        );

        $apiKey = $tokenAuthorization->getAuthDetails();

        $key = $apiKey->key;
        $value = $apiKey->value;

        $header = [
            'X-Public-ID'  => $key,
            'X-Secret-Key' => $value,
            'Content-Type' => 'application/json'
        ];

        $this->suiteDashContact = new SuiteDashContact(self::BASE_URL, $header);

        return SuiteDashActionHandler::executeAction(
            $machineSlug,
            $this->suiteDashContact,
            $contactId,
            $companyId,
            $choseContactId,
            $choseCompanyId,
            $subscribeFields,
            $contacts,
            $companies,
            $pageNumber
        );
    }
}
