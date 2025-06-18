<?php

namespace BitApps\Pi\src\Integrations\Woodpecker;

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;

if (!\defined('ABSPATH')) {
    exit;
}


class WoodpeckerProspect
{
    private $baseUrl;

    private $http;

    private $headers;

    /**
     * WoodpeckerProspect constructor.
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
     * Add Prospects to Campaign.
     *
     * @param mixed  $prospectToCampaignData
     *
     * @return array
     */
    public function addProspectsToCampaign($prospectToCampaignData)
    {
        $url = $this->baseUrl . '/add_prospects_campaign';
        $jsonBody = JSON::encode($prospectToCampaignData);
        $response = $this->http->request($url, 'POST', $jsonBody, $this->headers);

        return [
            'response'    => $response,
            'payload'     => $prospectToCampaignData,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Add Prospects to Prospect List.
     *
     * @param mixed $prospect
     *
     * @return array
     */
    public function addProspectsToList($prospect)
    {
        $url = $this->baseUrl . '/add_prospects_list';

        $jsonBody = JSON::encode($prospect);

        $response = $this->http->request($url, 'POST', $jsonBody, $this->headers);
        $statusCode = $this->http->getResponseCode();

        return [
            'response'    => $response,
            'payload'     => $jsonBody,
            'status_code' => $statusCode
        ];
    }

    /**
     * Get Campaign List.
     *
     * @return array
     */
    public function getCampaignList()
    {
        $url = $this->baseUrl . '/campaign_list';

        $response = $this->http->request($url, 'GET', null, $this->headers);

        return [
            'response'    => $response,
            'payload'     => null,
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Delete prospect.
     *
     * @param mixed $prospectId
     *
     * @return array
     */
    public function deleteProspect($prospectId)
    {
        $url = $this->baseUrl . '/prospects?id=' . $prospectId;

        $response = $this->http->request($url, 'DELETE', [], $this->headers);

        $statusCode = $this->http->getResponseCode();

        return [
            'response' => $response,
            'payload'  => [
                'prospects' => $prospectId
            ],
            'status_code' => $statusCode
        ];
    }

    /**
     * Get Campaign List.
     *
     * @param mixed $searchStatus
     *
     * @return array
     */
    public function searchProspect($searchStatus)
    {
        $url = $this->baseUrl . '/prospects?status=' . $searchStatus;

        $response = $this->http->request($url, 'GET', [], $this->headers);

        return [
            'response'    => $response,
            'payload'     => $response,
            'status_code' => $this->http->getResponseCode()
        ];
    }
}
