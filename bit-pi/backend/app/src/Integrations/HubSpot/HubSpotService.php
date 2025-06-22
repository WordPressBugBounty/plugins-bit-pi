<?php

namespace BitApps\Pi\src\Integrations\HubSpot;

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;

if (!\defined('ABSPATH')) {
    exit;
}
class HubSpotService
{
    private $baseUrl;

    private $http;

    private $headers;

    /**
     * HubSpot constructor.
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
     * Create a contact.
     *
     * @param mixed $fieldMapData
     * @param mixed $isUpdateContact
     *
     * @return array
     */
    public function createContact($isUpdateContact, $fieldMapData)
    {
        $endPoint = $this->baseUrl . '/crm/v3/objects/contacts';
        $sendContactData = JSON::encode($fieldMapData);
        $response = $this->http->request($endPoint, 'POST', $sendContactData, $this->headers);
        $email = $fieldMapData['properties']['email'];

        if ($isUpdateContact === true) {
            return $this->updateContact($email, $fieldMapData);
        }

        return [
            'response'    => $response,
            'payload'     => $sendContactData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Update a Contact.
     *
     * @param mixed $email
     * @param mixed $fieldMapData
     *
     * @return array
     */
    public function updateContact($email, $fieldMapData)
    {
        $endPoint = $this->baseUrl . '/crm/v3/objects/contacts/' . $email . '?idProperty=email';
        $updateContactData = JSON::encode($fieldMapData);
        $response = $this->http->request($endPoint, 'PATCH', $updateContactData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $updateContactData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Create a contact.
     *
     * @param mixed $fieldMapData
     * @param mixed $companyId
     * @param mixed $isUpdateCompany
     *
     * @return array
     */
    public function createCompany($companyId, $isUpdateCompany, $fieldMapData)
    {
        if ($isUpdateCompany === true) {
            return $this->updateCompany($companyId, $fieldMapData);
        }

        $endPoint = $this->baseUrl . '/crm/v3/objects/companies';
        $sendCompaniesData = JSON::encode($fieldMapData);
        $response = $this->http->request($endPoint, 'POST', $sendCompaniesData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $sendCompaniesData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Update a Company.
     *
     * @param mixed $fieldMapData
     * @param mixed $companyId
     *
     * @return array
     */
    public function updateCompany($companyId, $fieldMapData)
    {
        $endPoint = $this->baseUrl . '/crm/v3/objects/companies/' . $companyId;
        $updateCompanyData = JSON::encode($fieldMapData);
        $response = $this->http->request($endPoint, 'PATCH', $updateCompanyData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $updateCompanyData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Create a Deal.
     *
     * @param mixed $fieldMapData
     * @param mixed $dealId
     * @param mixed $isUpdateDeal
     *
     * @return array
     */
    public function createDeal($dealId, $isUpdateDeal, $fieldMapData)
    {
        if ($isUpdateDeal === true) {
            return $this->updateDeal($dealId, $fieldMapData);
        }

        $endPoint = $this->baseUrl . '/crm/v3/objects/deals';
        $sendDealData = JSON::encode($fieldMapData);
        $response = $this->http->request($endPoint, 'POST', $sendDealData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $sendDealData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Update a Deal.
     *
     * @param mixed $fieldMapData
     * @param mixed $dealId
     *
     * @return array
     */
    public function updateDeal($dealId, $fieldMapData)
    {
        $endPoint = $this->baseUrl . '/crm/v3/objects/deals/' . $dealId;
        $updateDealData = JSON::encode($fieldMapData);
        $response = $this->http->request($endPoint, 'PATCH', $updateDealData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $updateDealData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Add Contact To List.
     *
     * @param mixed $listId
     * @param mixed $emails
     *
     * @return array
     */
    public function addContactToList($listId, $emails)
    {
        $endPoint = $this->baseUrl . '/contacts/v1/lists/' . $listId . '/add';
        $addToListData = JSON::encode(['emails' => $emails]);

        $response = $this->http->request($endPoint, 'POST', $addToListData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $addToListData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Remove Contact from List.
     *
     * @param mixed $listId
     * @param mixed $emails
     *
     * @return array
     */
    public function removeContactFromList($listId, $emails)
    {
        $endPoint = $this->baseUrl . '/contacts/v1/lists/' . $listId . '/remove';
        $addToListData = JSON::encode(['emails' => $emails]);

        $response = $this->http->request($endPoint, 'POST', $addToListData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $addToListData,
            'status_code' => $this->http->getResponseCode()
        ];
    }
}
