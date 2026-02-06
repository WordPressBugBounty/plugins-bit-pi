<?php

namespace BitApps\Pi\src\Integrations\Sendy;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;
use InvalidArgumentException;

class SendyAction implements ActionInterface
{
    private NodeInfoProvider $nodeInfoProvider;

    private SendyService $sendyService;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeIntegrationAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeMachine(string $machineSlug, array $configs, array $fieldMapData)
    {
        $listId = $configs['list-id']['value'] ?? null;
        $brandId = $configs['brand-id']['value'] ?? null;

        switch ($machineSlug) {
            case 'addSubscriber':
                return $this->sendyService->addSubscriber($listId, $fieldMapData);

            case 'unsubscribe':
                return $this->sendyService->unsubscribe($listId, $fieldMapData);

            case 'deleteSubscriber':
                return $this->sendyService->deleteSubscriber($listId, $fieldMapData);

            case 'subscriptionStatus':
                return $this->sendyService->subscriptionStatus($listId, $fieldMapData);

            case 'subscriberCount':
                return $this->sendyService->subscriberCount($listId);

            case 'createCampaign':
                $listId = implode(',', $listId);

                return $this->sendyService->createCampaign($brandId, $listId, $fieldMapData);

            default:
                throw new InvalidArgumentException(\sprintf('Unknown Sendy machine slug: %s', $machineSlug));
        }
    }

    private function executeIntegrationAction(): array
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();
        $configs = $this->nodeInfoProvider->getFieldMapConfigs();
        $fieldMapData = $this->nodeInfoProvider->getFieldMapData();


        $apiKeyAuthorization = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::API_KEY,
            $configs['connection-id']
        );

        $apiKey = $apiKeyAuthorization->getAccessToken();
        $connection = $apiKeyAuthorization->getConnection();
        $sendyUrl = $connection->auth_details->extraData->sendyUrl;

        $httpClient = new HttpClient(
            [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ]
        );

        $this->sendyService = new SendyService($httpClient, $sendyUrl, $apiKey);

        return $this->executeMachine($machineSlug, $configs, $fieldMapData);
    }
}
