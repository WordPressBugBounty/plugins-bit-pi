<?php

namespace BitApps\Pi\src\Integrations\MailChimp;

use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!\defined('ABSPATH')) {
    exit;
}


class MailChimpAction implements ActionInterface
{
    private $mailChimpServices;

    private $nodeInfoProvider;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executeResponse = $this->executeMailChimpAction();

        if (isset($executeResponse['response']['addContact']->id) || isset($executeResponse['response']['addContact']->title) || isset($executeResponse['response']['updateContact']->id)) {
            return [
                'status' => 'success',
                'output' => $executeResponse['response'],
                'input'  => $executeResponse['payload'],
            ];
        }

        return [
            'status' => 'error',
            'output' => $executeResponse['response'],
            'input'  => $executeResponse['payload'],
        ];
    }

    private function executeMailChimpAction()
    {
        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');
        $fieldMapData = $this->nodeInfoProvider->getFieldMapRepeaters('field-map.value', false, true, 'mailchimpField', 'value');
        $addressFieldMapData = $this->nodeInfoProvider->getFieldMapRepeaters('address-field-map.value', false, true, 'mailchimpAddressField', 'value');
        $additionalData = $this->nodeInfoProvider->getFieldMapData();

        $dataCenter = $this->nodeInfoProvider->getData();
        $dataCenter = $dataCenter['db']['dataCenter'];

        $accessToken = AuthorizationFactory::getAuthorizationHandler(AuthorizationType::OAUTH2, $connectionId)->getAccessToken();

        $httpClient = new HttpClient(['headers' => ['Authorization' => $accessToken]]);
        $mailerLiteAction = $this->nodeInfoProvider->getMachineSlug();

        $this->mailChimpServices = new MailChimpServices($httpClient, $dataCenter);

        if ($mailerLiteAction === 'createSubscriber') {
            return $this->mailChimpServices->createSubscriber($fieldMapData, $addressFieldMapData, $additionalData);
        }
    }
}
