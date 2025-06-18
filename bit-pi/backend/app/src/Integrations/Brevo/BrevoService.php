<?php

namespace BitApps\Pi\src\Integrations\Brevo;

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;

if (!\defined('ABSPATH')) {
    exit;
}

class BrevoService
{
    private $baseUrl;

    private $http;

    private $headers;

    /**
     * BrevoService constructor.
     *
     * @param mixed $headers
     * @param mixed $baseUrl
     */
    public function __construct($baseUrl, $headers)
    {
        $this->baseUrl = $baseUrl;
        $this->http = new HttpClient();
        $this->headers = $headers;
    }

    /**
     * Create New Contact.
     *
     * @param mixed $fieldMapData
     *
     * @return array
     */
    public function createContact($fieldMapData)
    {
        $url = $this->baseUrl . '/contacts';
        $contactData = JSON::encode($fieldMapData);
        $response = $this->http->request($url, 'POST', $contactData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $contactData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Add Contact to the List.
     *
     * @param mixed $listId
     * @param mixed $emails
     *
     * @return array
     */
    public function addContactToList($listId, $emails)
    {
        $endPoint = $this->baseUrl . '/contacts/lists/' . $listId . '/contacts/add';
        $emailsData = JSON::encode(['emails' => $emails]);
        $response = $this->http->request($endPoint, 'POST', $emailsData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $emailsData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Delete Contact.
     *
     * @param mixed $email
     *
     * @return array
     */
    public function deleteContact($email)
    {
        $endPoint = $this->baseUrl . '/contacts/' . $email;
        $response = $this->http->request($endPoint, 'DELETE', [], $this->headers);

        return [
            'response'    => $response,
            'payload'     => ['email' => $email],
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Remove Contact From List.
     *
     * @param mixed $configs
     * @param mixed $emails
     *
     * @return array
     */
    public function removeContactFromList($configs, $emails)
    {
        $listId = $configs['list-id']['value'];

        if ($configs['remove-all']['value'] === 'true') {
            $emailsData = JSON::encode(['all' => true]);
        } else {
            $emailsData = JSON::encode(['emails' => $emails]);
        }

        $endPoint = $this->baseUrl . '/contacts/lists/' . $listId . '/contacts/remove';
        $response = $this->http->request($endPoint, 'POST', $emailsData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $emailsData,
            'status_code' => $this->http->getResponseCode()
        ];
    }
}
