<?php

namespace BitApps\Pi\src\Authorization\OAuth2;

use BitApps\Pi\Helpers\Hash;
use BitApps\Pi\src\Authorization\AbstractBaseAuthorization;

if (!\defined('ABSPATH')) {
    exit;
}


class OAuth2Authorization extends AbstractBaseAuthorization
{
    protected $connection;

    private $bodyParams;

    private $tokenDetails;

    private $refreshTokenUrl;

    public function __construct($connectionId)
    {
        parent::__construct($connectionId);
    }

    public function refreshAccessToken(): array
    {
        $tokenDetails = $this->tokenDetails;

        $response = $this->http->request($this->refreshTokenUrl ?? $tokenDetails->refreshTokenUrl, 'POST', $this->getBodyParams($tokenDetails));

        if ($this->http->getResponseCode() !== 200 || isset($response->error)) {
            return [
                'error'    => true,
                'message'  => $response->error ?? 'Token refresh failed',
                'response' => $response,
                'payload'  => $this->bodyParams,
            ];
        }

        $tokenDetails['access_token'] = Hash::encrypt($response->access_token);

        $tokenDetails['expires_in'] = $response->expires_in;

        $tokenDetails['generated_at'] = time();

        return $this->updateConnection($this->connection, $tokenDetails);
    }

    public function getAuthDetails(): array
    {
        $tokenDetails = $this->setAuthDetails();

        if (isset($tokenDetails['error'])) {
            return $tokenDetails;
        }

        if ($this->isTokenExpired($tokenDetails['generated_at'], $tokenDetails['expires_in'])) {
            return $this->refreshAccessToken();
        }

        return $this->tokenDetails;
    }

    public function getAccessToken()
    {
        $tokenDetails = $this->setAuthDetails();

        $accessToken = $tokenDetails['access_token'];

        if (isset($tokenDetails['error'])) {
            return $tokenDetails;
        }

        if ($this->isTokenExpired($tokenDetails['generated_at'], $tokenDetails['expires_in'])) {
            $newAuthDetails = $this->refreshAccessToken();

            if (isset($newAuthDetails['error'])) {
                return $newAuthDetails;
            }

            $accessToken = $newAuthDetails['access_token'];
        }

        return 'Bearer ' . Hash::decrypt($accessToken);
    }

    public function setBodyParams($bodyParams)
    {
        $this->bodyParams = $bodyParams;

        return $this;
    }

    public function setRefreshTokenUrl($refreshTokenUrl)
    {
        $this->refreshTokenUrl = $refreshTokenUrl;

        return $this;
    }

    private function setAuthDetails()
    {
        $this->connection = $this->getConnection();

        if (!$this->connection) {
            return [
                'error'   => true,
                'message' => 'Connection not found',
            ];
        }

        return $this->tokenDetails = (array) $this->connection->auth_details;
    }

    private function getBodyParams($tokenDetails)
    {
        return $this->bodyParams ?? [
            'grant_type'    => 'refresh_token',
            'client_id'     => $tokenDetails['client_id'],
            'client_secret' => Hash::decrypt($tokenDetails['client_secret']),
            'refresh_token' => Hash::decrypt($tokenDetails['refresh_token']),
        ];
    }
}
