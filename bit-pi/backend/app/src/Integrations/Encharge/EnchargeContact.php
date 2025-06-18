<?php

namespace BitApps\Pi\src\Integrations\Encharge;

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;

if (!\defined('ABSPATH')) {
    exit;
}


class EnchargeContact
{
    private $baseUrl;

    private $http;

    private $headers;

    /**
     * Encharge Service constructor.
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
     *
     * @return array
     */
    public function createNewContact($data)
    {
        $url = $this->baseUrl . '/people';

        $response = $this->http->request($url, 'POST', JSON::encode($data), $this->headers);

        return [
            'response'    => $response,
            'payload'     => $data,
            'status_code' => $this->http->getResponseCode()
        ];
    }
}
