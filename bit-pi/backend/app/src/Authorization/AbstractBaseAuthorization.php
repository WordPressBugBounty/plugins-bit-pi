<?php

namespace BitApps\Pi\src\Authorization;

use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\Helpers\Hash;
use BitApps\Pi\Model\Connection;

abstract class AbstractBaseAuthorization
{
    protected $connectionId;

    protected $http;

    protected $connection;

    public function __construct($connectionId)
    {
        $this->connectionId = $connectionId;
        $this->setConnection($connectionId);
        $this->http = new HttpClient();
    }

    abstract public function getAccessToken();

    public function getConnectionId(): int
    {
        return (int) $this->connectionId;
    }

    public function getConnection()
    {
        if (!$this->connection) {
            $this->setConnection($this->connectionId);
        }

        return $this->connection;
    }

    public function getAuthDetails()
    {
        $connection = $this->getConnection();

        $authDetails = $connection->auth_details;

        if (!$authDetails) {
            return;
        }

        $encryptKeys = explode(',', $connection->encrypt_keys);

        if ($encryptKeys === []) {
            return $authDetails;
        }

        foreach ($encryptKeys as $encryptKey) {
            if (isset($authDetails->{$encryptKey})) {
                $authDetails->{$encryptKey} = Hash::decrypt($authDetails->{$encryptKey});
            }
        }

        return $authDetails;
    }

    public function updateConnection($connection, $newTokenDetails): array
    {
        $save = $connection->update(
            [
                'auth_details' => $newTokenDetails
            ]
        )->save();

        if (!$save) {
            return [
                'error'   => true,
                'message' => 'connection update failed',
            ];
        }

        return $newTokenDetails;
    }

    public function isTokenExpired($generatedAt, $expiresIn): bool
    {
        if (!isset($generatedAt, $expiresIn) || $expiresIn <= 0) {
            return false;
        }

        return time() > (\intval($generatedAt) + $expiresIn - 30);
    }

    private function setConnection($connectionId)
    {
        $this->connection = Connection::select(['id', 'auth_details', 'encrypt_keys', 'auth_type'])->findOne(['id' => $connectionId]);
    }
}
