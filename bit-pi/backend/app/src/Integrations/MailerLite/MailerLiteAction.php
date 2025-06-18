<?php

namespace BitApps\Pi\src\Integrations\MailerLite;

use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!\defined('ABSPATH')) {
    exit;
}


class MailerLiteAction implements ActionInterface
{
    public const BASE_URL = 'https://connect.mailerlite.com/api/';

    private $subscriber;

    private $nodeInfoProvider;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executeResponse = $this->executeMailerLiteAction();

        if (isset($executeResponse['response']['createSubscriber']->data->id) || isset($executeResponse['response']['updateSubscriber']->data->id)) {
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

    private function executeMailerLiteAction()
    {
        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');
        $fieldMapData = $this->nodeInfoProvider->getFieldMapRepeaters('field-map.value', false, true, 'mailerLiteField', 'value');
        $additionalData = $this->nodeInfoProvider->getFieldMapData();

        $accessToken = AuthorizationFactory::getAuthorizationHandler(AuthorizationType::BEARER_TOKEN, $connectionId)->getAccessToken();
        $httpClient = new HttpClient(['headers' => ['Authorization' => $accessToken]]);
        $mailerLiteAction = $this->nodeInfoProvider->getMachineSlug();

        $this->subscriber = new MailerLiteSubscriber($httpClient, static::BASE_URL);

        if ($mailerLiteAction === 'createSubscriber') {
            return $this->subscriber->createSubscriber($fieldMapData, $additionalData);
        }
    }
}
