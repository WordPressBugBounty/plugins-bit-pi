<?php

namespace BitApps\Pi\src\Integrations\WhatsApp;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

class WhatsAppAction implements ActionInterface
{
    private NodeInfoProvider $nodeInfoProvider;

    private WhatsAppService $whatsAppService;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeWhatsAppAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function setNodeInfoProperties()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();
        $configs = $this->nodeInfoProvider->getFieldMapConfigs();
        $fieldMapData = $this->nodeInfoProvider->getFieldMapData();

        $fieldMapData = array_merge(['messaging_product' => 'whatsapp'], $fieldMapData);

        $tokenAuthorization = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::BEARER_TOKEN,
            $configs['connection-id']
        );

        $token = $tokenAuthorization->getAccessToken();

        $connection = $tokenAuthorization->getConnection();
        $phoneNumberId = $connection->auth_details->extraData->phoneNumberId;

        $header = [
            'Content-Type'  => 'application/json',
            'Authorization' => $token
        ];

        return [
            'machineSlug'   => $machineSlug,
            'configs'       => $configs,
            'fieldMapData'  => $fieldMapData,
            'header'        => $header,
            'phoneNumberId' => $phoneNumberId

        ];
    }

    private function executeAction(
        array $whatsAppData
    ) {
        switch ($whatsAppData['machineSlug']) {
            case 'sendTemplateMessage':
                $whatsAppData['fieldMapData']['type'] = 'template';

                return $this->whatsAppService->sendTemplateMessage($whatsAppData['fieldMapData'], $whatsAppData['phoneNumberId']);

            case 'sendMessage':
                $whatsAppData['fieldMapData']['type'] = 'text';

                return $this->whatsAppService->sendMessage($whatsAppData['fieldMapData'], $whatsAppData['phoneNumberId']);

            case 'sendImage':
                $whatsAppData['fieldMapData']['type'] = 'image';

                return $this->whatsAppService->sendImage($whatsAppData['fieldMapData'], $whatsAppData['phoneNumberId']);

            case 'sendDocument':
                $whatsAppData['fieldMapData']['type'] = 'document';

                return $this->whatsAppService->sendDocument($whatsAppData['fieldMapData'], $whatsAppData['phoneNumberId']);

            case 'sendVideo':
                $whatsAppData['fieldMapData']['type'] = 'video';

                return $this->whatsAppService->sendVideo($whatsAppData['fieldMapData'], $whatsAppData['phoneNumberId']);

            case 'sendAudio':
                $whatsAppData['fieldMapData']['type'] = 'audio';

                return $this->whatsAppService->sendAudio($whatsAppData['fieldMapData'], $whatsAppData['phoneNumberId']);

            case 'sendLocation':
                $whatsAppData['fieldMapData']['type'] = 'location';

                return $this->whatsAppService->sendLocation($whatsAppData['fieldMapData'], $whatsAppData['phoneNumberId']);
        }
    }

    private function executeWhatsAppAction(): array
    {
        $whatsAppData = $this->setNodeInfoProperties();
        $this->whatsAppService = new WhatsAppService($whatsAppData['header']);

        return $this->executeAction(
            $whatsAppData
        );
    }
}
