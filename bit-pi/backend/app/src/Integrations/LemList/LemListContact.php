<?php

namespace BitApps\Pi\src\Integrations\LemList;

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;

if (!\defined('ABSPATH')) {
    exit;
}


class LemListContact
{
    private $baseUrl;

    private $http;

    private $headers;

    /**
     * LemListContact constructor.
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
     * @param mixed $campaignId
     *
     * @return array
     */
    public function createNewContact($data, $campaignId)
    {
        $url = $this->baseUrl . '/' . $campaignId . '/leads';

        $response = $this->http->request($url, 'POST', JSON::encode($data), $this->headers);

        return [
            'response'    => $response,
            'payload'     => $data,
            'status_code' => $this->http->getResponseCode()
        ];
    }
}
