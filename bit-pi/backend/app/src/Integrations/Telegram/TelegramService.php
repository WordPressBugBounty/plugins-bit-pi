<?php

namespace BitApps\Pi\src\Integrations\Telegram;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;

class TelegramService
{
    private $baseUrl;

    private $http;

    private $headers;

    /**
     * TelegramService constructor.
     *
     * @param string $baseUrl
     * @param array  $headers
     */
    public function __construct($baseUrl, $headers)
    {
        $this->baseUrl = $baseUrl;
        $this->http = new HttpClient();
        $this->headers = $headers;
    }

    /**
     * Send Message or Reply.
     *
     * @param mixed $accessToken
     * @param mixed $fieldMapData
     *
     * @return array
     */
    public function sendMessage($accessToken, $fieldMapData)
    {
        $endPoint = $this->baseUrl . $accessToken . '/sendMessage';
        $sendMessageData = JSON::encode($fieldMapData);
        $response = $this->http->request($endPoint, 'POST', $sendMessageData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $sendMessageData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Send Poll.
     *
     * @param mixed $accessToken
     * @param mixed $fieldMapData
     *
     * @return array
     */
    public function sendPoll($accessToken, $fieldMapData)
    {
        $endPoint = $this->baseUrl . $accessToken . '/sendPoll';
        $sendPollData = JSON::encode($fieldMapData);
        $response = $this->http->request($endPoint, 'POST', $sendPollData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $sendPollData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Send Contact.
     *
     * @param mixed $accessToken
     * @param mixed $fieldMapData
     *
     * @return array
     */
    public function sendContact($accessToken, $fieldMapData)
    {
        $endPoint = $this->baseUrl . $accessToken . '/sendContact';
        $sendContactData = JSON::encode($fieldMapData);
        $response = $this->http->request($endPoint, 'POST', $sendContactData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $sendContactData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Create Invite Link.
     *
     * @param mixed $accessToken
     * @param mixed $fieldMapData
     *
     * @return array
     */
    public function createInviteLink($accessToken, $fieldMapData)
    {
        $endPoint = $this->baseUrl . $accessToken . '/createChatInviteLink';
        $sendInviteLinkData = JSON::encode($fieldMapData);
        $response = $this->http->request($endPoint, 'POST', $sendInviteLinkData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $sendInviteLinkData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Revoke Invite Link.
     *
     * @param mixed $accessToken
     * @param mixed $fieldMapData
     *
     * @return array
     */
    public function revokeInviteLink($accessToken, $fieldMapData)
    {
        $endPoint = $this->baseUrl . $accessToken . '/revokeChatInviteLink';
        $revokeInviteLinkData = JSON::encode($fieldMapData);
        $response = $this->http->request($endPoint, 'POST', $revokeInviteLinkData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $revokeInviteLinkData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Ban a Member.
     *
     * @param mixed $accessToken
     * @param mixed $fieldMapData
     *
     * @return array
     */
    public function banUser($accessToken, $fieldMapData)
    {
        $endPoint = $this->baseUrl . $accessToken . '/banChatMember';
        $banMemberData = JSON::encode($fieldMapData);
        $response = $this->http->request($endPoint, 'POST', $banMemberData, $this->headers);


        return [
            'response'    => $response,
            'payload'     => $banMemberData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Unban a Member.
     *
     * @param mixed $accessToken
     * @param mixed $fieldMapData
     *
     * @return array
     */
    public function unbanUser($accessToken, $fieldMapData)
    {
        $endPoint = $this->baseUrl . $accessToken . '/unbanChatMember';
        $unbanMemberData = JSON::encode($fieldMapData);
        $response = $this->http->request($endPoint, 'POST', $unbanMemberData, $this->headers);


        return [
            'response'    => $response,
            'payload'     => $unbanMemberData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Send Media.
     *
     * @param mixed $accessToken
     * @param mixed $fieldMapData
     * @param mixed $choseMedia
     *
     * @return array
     */
    public function sendMedia($accessToken, $fieldMapData, $choseMedia)
    {
        $choseMedia = ucfirst($choseMedia);
        $endPoint = $this->baseUrl . $accessToken . '/send' . $choseMedia;
        $mediaData = JSON::encode($fieldMapData);
        $response = $this->http->request($endPoint, 'POST', $mediaData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $mediaData,
            'status_code' => $this->http->getResponseCode()
        ];
    }
}
