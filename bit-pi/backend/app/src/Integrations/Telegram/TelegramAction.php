<?php

namespace BitApps\Pi\src\Integrations\Telegram;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Integrations\Telegram\helpers\TelegramActionHandler;
use BitApps\Pi\src\Interfaces\ActionInterface;

class TelegramAction implements ActionInterface
{
    private const BASE_URL = 'https://api.telegram.org/bot';

    private NodeInfoProvider $nodeInfoProvider;

    private TelegramService $telegramService;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeTelegramAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeTelegramAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();
        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');
        $replyMarkup = $this->nodeInfoProvider->getFieldMapRepeaters('reply-markup.value', false, false);
        $optionsList = $this->nodeInfoProvider->getFieldMapRepeaters('options-list.value', false, false);
        $choseMedia = $this->nodeInfoProvider->getFieldMapConfigs('chose-media.value');
        $fieldMapData = $this->nodeInfoProvider->getFieldMapData();

        $fieldMapData = TelegramActionHandler::handleCondtion($fieldMapData, $replyMarkup, $optionsList, $choseMedia);

        $tokenAuthorization = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::API_KEY,
            $connectionId
        );

        $accessToken = $tokenAuthorization->getAccessToken();

        $header = [
            'Content-Type' => 'application/json',
        ];

        $this->telegramService = new TelegramService(self::BASE_URL, $header);

        return TelegramActionHandler::executeAction(
            $machineSlug,
            $this->telegramService,
            $accessToken,
            $fieldMapData,
            $choseMedia
        );
    }
}
