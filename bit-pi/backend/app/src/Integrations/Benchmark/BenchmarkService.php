<?php

namespace BitApps\Pi\src\Integrations\Benchmark;

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;

if (!\defined('ABSPATH')) {
    exit;
}


class BenchmarkService
{
    private $baseUrl;

    private $http;

    private $headers;

    /**
     * BenchmarkService constructor.
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
     * @param mixed $data
     * @param mixed $listId
     *
     * @return array
     */
    public function createNewContact($data, $listId)
    {
        $url = $this->baseUrl . '/Contact/' . $listId . '/ContactDetails';

        $response = $this->http->request($url, 'POST', JSON::encode($data), $this->headers);

        return [
            'response'    => $response,
            'payload'     => $data,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Update Contact.
     *
     * @param mixed $data
     * @param mixed $listId
     * @param mixed $contactId
     *
     * @return array
     */
    public function updateContact($data, $listId, $contactId)
    {
        $url = $this->baseUrl . '/Contact/' . $listId . '/ContactDetails/' . $contactId;

        $response = $this->http->request($url, 'PATCH', JSON::encode($data), $this->headers);

        return [
            'response'    => $response,
            'payload'     => $data,
            'status_code' => $this->http->getResponseCode()
        ];
    }
}
