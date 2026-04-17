<?php

namespace BitApps\Pi\src\Integrations\Telegram\helpers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\src\Integrations\Telegram\TelegramService;

class TelegramActionHandler
{
    public static function handleCondtion($fieldMapData, $replyMarkup, $optionsList, $choseMedia)
    {
        if (!empty($replyMarkup[0])) {
            $inlineKeyboard = [];

            foreach ($replyMarkup as $button) {
                $inlineKeyboard[] = [$button];
            }

            $reply['reply_markup'] = [
                'inline_keyboard' => $inlineKeyboard
            ];

            $fieldMapData = array_merge($fieldMapData, $reply);
        }

        if (!empty($optionsList[0])) {
            $result = array_map(
                function ($item) {
                    return $item['text'];
                },
                $optionsList
            );
            $options['options'] = $result;
            $fieldMapData = array_merge($fieldMapData, $options);
        }

        if (!\is_array($choseMedia)) {
            $fieldMapData[$choseMedia] = $fieldMapData['url'];
            unset($fieldMapData['url']);
        }

        return $fieldMapData;
    }

    public static function executeAction(
        $machineSlug,
        TelegramService $telegramService,
        $accessToken,
        array $fieldMapData,
        $choseMedia
    ) {
        switch ($machineSlug) {
            case 'sendOrReplyMessage':
                return $telegramService->sendMessage($accessToken, $fieldMapData);

            case 'sendPoll':
                return $telegramService->sendPoll($accessToken, $fieldMapData);

            case 'sendContact':
                return $telegramService->sendContact($accessToken, $fieldMapData);

            case 'createInviteLink':
                return $telegramService->createInviteLink($accessToken, $fieldMapData);

            case 'revokeInviteLink':
                return $telegramService->revokeInviteLink($accessToken, $fieldMapData);

            case 'banUser':
                return $telegramService->banUser($accessToken, $fieldMapData);

            case 'unbanUser':
                return $telegramService->unbanUser($accessToken, $fieldMapData);

            case 'sendMedia':
                return $telegramService->sendMedia($accessToken, $fieldMapData, $choseMedia);
        }
    }
}
