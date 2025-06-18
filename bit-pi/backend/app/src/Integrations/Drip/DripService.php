<?php

namespace BitApps\Pi\src\Integrations\Drip;

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;

if (!\defined('ABSPATH')) {
    exit;
}


class DripService
{
    private $baseUrl;

    private $http;

    private $headers;

    /**
     * Drip Service constructor.
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
     * Create or Update Contact.
     *
     * @param mixed $data
     * @param mixed $accountListId
     *
     * @return array
     */
    public function createOrUpdateContact($data, $accountListId)
    {
        $url = $this->baseUrl . '/' . $accountListId . '/subscribers';

        $response = $this->http->request($url, 'POST', JSON::encode($data), $this->headers);

        return [
            'response'    => $response,
            'payload'     => $data,
            'status_code' => $this->http->getResponseCode()
        ];
    }
}
