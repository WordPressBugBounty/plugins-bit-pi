<?php

namespace BitApps\Pi\src\Integrations\SuiteDash;

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;

if (!\defined('ABSPATH')) {
    exit;
}


class SuiteDashContact
{
    private $baseUrl;

    private $http;

    private $headers;

    /**
     * SuiteDash constructor.
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
     * @param mixed $createContact
     *
     * @return array
     */
    public function createContact($createContact)
    {
        $url = $this->baseUrl . '/contact';
        $jsonBody = JSON::encode($createContact);
        $response = $this->http->request($url, 'POST', $jsonBody, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $jsonBody,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Get contact List.
     *
     * @param mixed $pageNumber
     *
     * @return array
     */
    public function listContacts($pageNumber)
    {
        $url = $this->baseUrl . '/contacts?page=' . $pageNumber;

        $response = $this->http->request($url, 'GET', [], $this->headers);

        $statusCode = $this->http->getResponseCode();

        return [
            'response'    => $response,
            'payload'     => [],
            'status_code' => $statusCode
        ];
    }

    /**
     * Get Specific Contact.
     *
     * @param mixed $contactId
     *
     * @return array
     */
    public function getAContact($contactId)
    {
        $url = $this->baseUrl . '/contact/' . $contactId;

        $response = $this->http->request($url, 'GET', [], $this->headers);

        return [
            'response'    => $response,
            'payload'     => [],
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Update a Contact.
     *
     * @param mixed $contactId
     * @param mixed $contactDetails
     *
     * @return array
     */
    public function updateAContact($contactId, $contactDetails)
    {
        unset($contactDetails['role']);
        $url = $this->baseUrl . '/contact/' . $contactId;
        $jsonBody = JSON::encode($contactDetails);

        $response = $this->http->request($url, 'PUT', $jsonBody, $this->headers);
        $statusCode = $this->http->getResponseCode();

        return [
            'response'    => $response,
            'payload'     => $jsonBody,
            'status_code' => $statusCode
        ];
    }

    /**
     * Create a company.
     *
     * @param mixed $createContact
     *
     * @return array
     */
    public function createCompany($createContact)
    {
        $url = $this->baseUrl . '/company';
        $jsonBody = JSON::encode($createContact);
        $response = $this->http->request($url, 'POST', $jsonBody, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $jsonBody,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Get company List.
     *
     * @param mixed $pageNumber
     *
     * @return array
     */
    public function listCompanies($pageNumber)
    {
        $url = $this->baseUrl . '/companies?page=' . $pageNumber;

        $response = $this->http->request($url, 'GET', [], $this->headers);

        $statusCode = $this->http->getResponseCode();

        return [
            'response'    => $response,
            'payload'     => [],
            'status_code' => $statusCode
        ];
    }

    /**
     * Get Specific company.
     *
     * @param mixed $companyId
     *
     * @return array
     */
    public function getACompany($companyId)
    {
        $url = $this->baseUrl . '/company/' . $companyId;

        $response = $this->http->request($url, 'GET', [], $this->headers);

        return [
            'response'    => $response,
            'payload'     => [],
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Update a company.
     *
     * @param mixed $companyId
     * @param mixed $companyDetails
     *
     * @return array
     */
    public function updateACompany($companyId, $companyDetails)
    {
        unset($companyDetails['role'], $companyDetails['primaryContact']);
        $url = $this->baseUrl . '/company/' . $companyId;
        $jsonBody = JSON::encode($companyDetails);

        $response = $this->http->request($url, 'PUT', $jsonBody, $this->headers);
        $statusCode = $this->http->getResponseCode();

        return [
            'response'    => $response,
            'payload'     => $jsonBody,
            'status_code' => $statusCode
        ];
    }

    /**
     * Subscribe to marketing.
     *
     * @param mixed $subscribeFields
     *
     * @return array
     */
    public function subscribeMarketing($subscribeFields)
    {
        $url = $this->baseUrl . '/marketing/subscribe';
        $jsonBody = JSON::encode($subscribeFields);
        $response = $this->http->request($url, 'POST', $jsonBody, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $jsonBody,
            'status_code' => $this->http->getResponseCode()
        ];
    }
}
