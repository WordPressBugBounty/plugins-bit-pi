<?php

namespace BitApps\Pi\src\Integrations\BitConnect\Services;

use BitApps\Pi\src\Flow\NodeInfoProvider;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

abstract class BaseService
{
    protected $nodeInfoProvider;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    /**
     * Bit Connect puts the HTTP status its REST route would send in the error data.
     */
    protected function getErrorStatus(WP_Error $error): int
    {
        $errorData = $error->get_error_data();

        return \is_array($errorData) && isset($errorData['status']) ? (int) $errorData['status'] : 400;
    }

    protected function getPageArgs(array $fieldMappings): array
    {
        $limit = (int) ($fieldMappings['limit'] ?? 0);
        $page = (int) ($fieldMappings['page'] ?? 0);

        return [
            'limit' => $limit > 0 ? min($limit, 100) : 20,
            'page'  => max($page, 1),
        ];
    }
}
