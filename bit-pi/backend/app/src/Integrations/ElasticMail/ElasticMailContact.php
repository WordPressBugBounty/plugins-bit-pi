<?php

namespace BitApps\Pi\src\Integrations\ElasticMail;

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;

if (!\defined('ABSPATH')) {
    exit;
}


class ElasticMailContact
{
    private $baseUrl;

    private $http;

    private $headers;

    /**
     * Elastic mail Service constructor.
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
     * @param mixed $listNamesForQueryParams
     *
     * @return array
     */
    public function createNewContact($data, $listNamesForQueryParams)
    {
        $url = $this->baseUrl . '/contacts?' . $listNamesForQueryParams;

        $jsonData = JSON::encode([(object) $data]);

        $response = $this->http->request($url, 'POST', $jsonData, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $data,
            'status_code' => $this->http->getResponseCode()
        ];
    }
}
