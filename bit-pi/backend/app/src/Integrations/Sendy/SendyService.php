<?php

namespace BitApps\Pi\src\Integrations\Sendy;

use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}


final class SendyService
{
    private HttpClient $http;

    private string $baseUrl;

    private string $apiKey;

    /**
     * SendyService constructor.
     */
    public function __construct(HttpClient $httpClient, string $baseUrl, string $apiKey)
    {
        $this->http = $httpClient;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
    }

    /**
     * Add a new subscriber to a list.
     *
     * @param string $listId
     * @param array  $fieldMapData
     *
     * @return array
     */
    public function addSubscriber($listId, $fieldMapData)
    {
        $endPoint = $this->baseUrl . '/subscribe';


        $bodyParams = [
            'list'      => $listId,
            'api_key'   => $this->apiKey,
            'boolean'   => 'true',
            'email'     => $fieldMapData['email'] ?? null,
            'name'      => $fieldMapData['name'] ?? null,
            'country'   => $fieldMapData['country'] ?? null,
            'gdpr'      => isset($fieldMapData['gdpr']) && $fieldMapData['gdpr'] ? 'true' : null,
            'silent'    => isset($fieldMapData['silent']) && $fieldMapData['silent'] ? 'true' : null,
            'hp'        => isset($fieldMapData['hp']) && $fieldMapData['hp'] ? 'true' : null,
            'ipaddress' => $fieldMapData['ipaddress'] ?? null,
            'referrer'  => $fieldMapData['referrer'] ?? null,
        ];

        $bodyParams = array_filter($bodyParams);
        $response = $this->http->request($endPoint, 'POST', http_build_query($bodyParams));

        return [
            'response' => $response,
            'payload'  => [
                'list_id' => $listId,
                'email'   => $fieldMapData['email'] ?? null
            ],
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Unsubscribe an email from a list.
     *
     * @param string $listId
     * @param array  $fieldMapData
     *
     * @return array
     */
    public function unsubscribe($listId, $fieldMapData)
    {
        $endPoint = $this->baseUrl . '/unsubscribe';

        $bodyParams = [
            'email'   => $fieldMapData['email'] ?? null,
            'list'    => $listId,
            'api_key' => $this->apiKey,
            'boolean' => 'true'
        ];

        $response = $this->http->request($endPoint, 'POST', http_build_query($bodyParams));

        return [
            'response' => $response,
            'payload'  => [
                'list_id' => $listId,
                'email'   => $fieldMapData['email'] ?? null
            ],
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Delete a subscriber from a list permanently.
     *
     * @param string $listId
     * @param array  $fieldMapData
     *
     * @return array
     */
    public function deleteSubscriber($listId, $fieldMapData)
    {
        $endPoint = $this->baseUrl . '/api/subscribers/delete.php';

        $bodyParams = [
            'email'   => $fieldMapData['email'] ?? null,
            'list_id' => $listId,
            'api_key' => $this->apiKey
        ];

        $response = $this->http->request($endPoint, 'POST', http_build_query($bodyParams));

        return [
            'response' => $response,
            'payload'  => [
                'list_id' => $listId,
                'email'   => $fieldMapData['email'] ?? null
            ],
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Get subscription status of an email in a list.
     *
     * @param string $listId
     * @param array  $fieldMapData
     *
     * @return array
     */
    public function subscriptionStatus($listId, $fieldMapData)
    {
        $endPoint = $this->baseUrl . '/api/subscribers/subscription-status.php';

        $bodyParams = [
            'email'   => $fieldMapData['email'] ?? null,
            'list_id' => $listId,
            'api_key' => $this->apiKey
        ];

        $response = $this->http->request($endPoint, 'POST', http_build_query($bodyParams));

        return [
            'response' => $response,
            'payload'  => [
                'list_id' => $listId,
                'email'   => $fieldMapData['email'] ?? null
            ],
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Get active subscriber count for a list.
     *
     * @param string $listId
     *
     * @return array
     */
    public function subscriberCount($listId)
    {
        $endPoint = $this->baseUrl . '/api/subscribers/active-subscriber-count.php';

        $bodyParams = [
            'list_id' => $listId,
            'api_key' => $this->apiKey
        ];

        $response = $this->http->request($endPoint, 'POST', http_build_query($bodyParams));

        return [
            'response'    => $response,
            'payload'     => ['list_id' => $listId],
            'status_code' => $this->http->getResponseCode()
        ];
    }

    /**
     * Create a campaign.
     *
     * @param string $brandId
     * @param array  $fieldMapData
     * @param mixed $listId
     *
     * @return array
     */
    public function createCampaign($brandId, $listId, $fieldMapData)
    {
        $endPoint = $this->baseUrl . '/api/campaigns/create.php';

        $bodyParams = [
            'api_key'              => $this->apiKey,
            'from_name'            => $fieldMapData['from_name'] ?? null,
            'from_email'           => $fieldMapData['from_email'] ?? null,
            'reply_to'             => $fieldMapData['reply_to'] ?? null,
            'title'                => $fieldMapData['title'] ?? null,
            'subject'              => $fieldMapData['subject'] ?? null,
            'html_text'            => $fieldMapData['html_text'] ?? null,
            'plain_text'           => $fieldMapData['plain_text'] ?? null,
            'list_ids'             => $listId ?? null,
            'segment_ids'          => $fieldMapData['segment_ids'] ?? null,
            'exclude_list_ids'     => $fieldMapData['exclude_list_ids'] ?? null,
            'exclude_segments_ids' => $fieldMapData['exclude_segments_ids'] ?? null,
            'query_string'         => $fieldMapData['query_string'] ?? null,
            'send_campaign'        => isset($fieldMapData['send_campaign']) && $fieldMapData['send_campaign'] ? 1 : 0,
            'track_opens'          => isset($fieldMapData['track_opens']) && $fieldMapData['track_opens'] ? 1 : 0,
            'track_clicks'         => isset($fieldMapData['track_clicks']) && $fieldMapData['track_clicks'] ? 1 : 0,
        ];

        if ($bodyParams['send_campaign'] === 0) {
            $bodyParams['brand_id'] = $brandId;
        }

        $bodyParams = array_filter($bodyParams);
        $response = $this->http->request($endPoint, 'POST', http_build_query($bodyParams));

        return [
            'response' => $response,
            'payload'  => [
                'brand_id' => $brandId,
                'list_id'  => $listId,
                'title'    => $fieldMapData['title'] ?? null
            ],
            'status_code' => $this->http->getResponseCode()
        ];
    }
}
