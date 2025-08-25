<?php

namespace BitApps\Pi\src\Integrations\MailChimp;

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;

if (!\defined('ABSPATH')) {
    exit;
}
class MailChimpService
{
    private $http;

    private $dataCenter;

    /**
     * MailChimp Constructor.
     *
     * @param mixed $httpClient
     * @param mixed $dataCenter
     */
    public function __construct($httpClient, $dataCenter)
    {
        $this->http = $httpClient;
        $this->dataCenter = $dataCenter;
    }

    /**
     * MailChimp Api Endpoint.
     */
    public function baseURL()
    {
        return 'https://' . $this->dataCenter . '.api.mailchimp.com/3.0';
    }

    /**
     * Add or Update a Member To List.
     *
     * @param string $listId
     * @param string $memberEmail
     * @param mixed $configs
     * @param mixed $fieldMapData
     *
     * @return array
     */
    public function addUpdateMember($listId, $memberEmail, $fieldMapData, $configs)
    {
        unset($fieldMapData['select-audience']);
        $subscriberHash = md5(strtolower($memberEmail));


        if ($configs['member-update-switch']['value'] === false) {
            $endPoint = $this->baseURL() . "/lists/{$listId}/members";
            $response = $this->http->request($endPoint, 'POST', JSON::encode($fieldMapData));
        } else {
            $endPoint = $this->baseURL() . "/lists/{$listId}/members/{$subscriberHash}";
            $response = $this->http->request($endPoint, 'PATCH', JSON::encode($fieldMapData));
        }

        return [
            'response'    => $response,
            'payload'     => $fieldMapData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Add/Remove Member Tag.
     *
     * @param string $listId
     * @param array $tagFieldMapData
     * @param string $memberEmail
     *
     * @return array
     */
    public function addRemoveMemberTag($listId, $memberEmail, $tagFieldMapData)
    {
        $subscriberHash = md5(strtolower($memberEmail));
        $endPoint = $this->baseURL() . "/lists/{$listId}/members/{$subscriberHash}/tags";
        $response = $this->http->request($endPoint, 'POST', JSON::encode(['tags' => $tagFieldMapData]));

        return [
            'response'    => $response,
            'payload'     => ['tags' => $tagFieldMapData],
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Add Member Note.
     *
     * @param string $listId
     * @param string $memberEmail
     * @param string $noteContent
     *
     * @return array
     */
    public function addMemberNote($listId, $memberEmail, $noteContent)
    {
        $subscriberHash = md5(strtolower($memberEmail));
        $endPoint = $this->baseURL() . "/lists/{$listId}/members/{$subscriberHash}/notes";
        $response = $this->http->request($endPoint, 'POST', JSON::encode(['note' => $noteContent]));

        return [
            'response'    => $response,
            'payload'     => ['note' => $noteContent],
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Delete Member From List.
     *
     * @param string $listId
     * @param string $memberEmail
     *
     * @return array
     */
    public function deleteMemberFromList($listId, $memberEmail)
    {
        $subscriberHash = md5(strtolower($memberEmail));
        $endPoint = $this->baseURL() . "/lists/{$listId}/members/{$subscriberHash}";

        $response = $this->http->request($endPoint, 'DELETE', []);

        return [
            'response' => $response,
            'payload'  => [
                'Email' => $memberEmail
            ],
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Get a Member From List.
     *
     * @param string $listId
     * @param string $memberEmail
     *
     * @return array
     */
    public function getMemberFromList($listId, $memberEmail)
    {
        $subscriberHash = md5(strtolower($memberEmail));
        $endPoint = $this->baseURL() . "/lists/{$listId}/members/{$subscriberHash}";

        $response = $this->http->request($endPoint, 'GET', []);

        return [
            'response'    => $response,
            'payload'     => ['Email' => $memberEmail],
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Get Members From List.
     *
     * @param string $listId
     * @param string $status
     * @param int $count
     *
     * @return array
     */
    public function getMembersFromList($listId, $status = null, $count = null)
    {
        $query = [];

        if (!\is_null($status)) {
            $query['status'] = $status;
        }

        if (!\is_null($count)) {
            $query['count'] = $count;
        }

        $endPoint = $this->baseURL() . "/lists/{$listId}/members";

        if (!empty($query)) {
            $endPoint = add_query_arg($query, $this->baseURL() . "/lists/{$listId}/members");
        }

        $response = $this->http->request($endPoint, 'GET', []);

        return [
            'response'    => $response,
            'payload'     => null,
            'status_code' => $this->http->getResponseCode(),
        ];
    }
}
