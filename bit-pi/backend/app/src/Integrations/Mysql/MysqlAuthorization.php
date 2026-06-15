<?php

namespace BitApps\Pi\src\Integrations\Mysql;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\src\Authorization\AbstractBaseAuthorization;

/**
 * MySQL "authorization" resolves the stored connection into a direct DB config.
 *
 * The password is the only encrypted secret (auth_details.value); host, port,
 * database and username are stored as plaintext extraData. This is a CUSTOM auth
 * type — invoked via AuthorizationFactory::getAuthorizationHandler(CUSTOM, id, 'mysql').
 */
class MysqlAuthorization extends AbstractBaseAuthorization
{
    private const DEFAULT_PORT = 3306;

    private ?object $cachedAuthDetails = null;

    /**
     * Return the decrypted DB connection config (used to open a PDO connection).
     *
     * @return array{error:bool,message:string}|array{host:string,port:int,database:string,username:string,password:string}
     */
    public function getAccessToken()
    {
        if (!$this->getConnection()) {
            return [
                'error'   => true,
                'message' => 'Connection not found',
            ];
        }

        $authDetails = $this->getCachedAuthDetails();
        $extraData = (object) ($authDetails->extraData ?? []);

        return [
            'host'           => $extraData->host ?? 'localhost',
            'port'           => (int) ($extraData->port ?? self::DEFAULT_PORT),
            'database'       => $extraData->database ?? '',
            'username'       => $extraData->username ?? '',
            'password'       => $authDetails->value ?? '',
            'charset'        => $extraData->charset ?? 'utf8mb4',
            'connectTimeout' => $extraData->connectTimeout ?? null,
            'sslCa'          => $extraData->sslCa ?? '',
            'sslKey'         => $extraData->sslKey ?? '',
            'sslCert'        => $extraData->sslCert ?? '',
        ];
    }

    public function getCachedAuthDetails(): ?object
    {
        if ($this->cachedAuthDetails !== null) {
            return $this->cachedAuthDetails;
        }

        $this->cachedAuthDetails = $this->getAuthDetails();

        return $this->cachedAuthDetails;
    }
}
