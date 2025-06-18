<?php

namespace BitApps\Pi\src\Integrations\RapidMail;

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;

if (!\defined('ABSPATH')) {
    exit;
}


class RapidMailService
{
    private $baseUrl;

    private $http;

    private $headers;

    /**
     * Rapid mail Service constructor.
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
    public function addRecipient($data)
    {
        $url = $this->baseUrl . '/recipients';

        $jsonData = JSON::encode((object) $data);

        $response = $this->http->request($url, 'POST', $jsonData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $data,
            'status_code' => $this->http->getResponseCode()
        ];
    }
}
