<?php

namespace BitApps\Pi\src\Integrations\BitCrm\Services;

use BitApps\Crm\Model\Link;
use BitApps\Pi\src\Integrations\BitCrm\BitCrmHelper;
use BitApps\Pi\src\Integrations\IntegrationHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Read-only access to the links Bit CRM keeps against leads, contacts,
 * companies and deals. Bit CRM has no public write API for links.
 */
final class LinkService extends BaseService
{
    public function getAllLinks()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Link')) {
            return $error;
        }

        $fields = $this->fields();
        $module = $fields['module'] ?? '';
        $entityId = (int) ($fields['entity_id'] ?? 0);

        $payload = ['module' => $module, 'entity_id' => $entityId];

        if (empty($module)) {
            $links = Link::all();
        } else {
            $query = Link::where('module', $module);

            if (!empty($entityId)) {
                $query = $query->where('entity_id', $entityId);
            }

            $links = $query->get();
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => $links ? $links->toArray() : []];
    }

    public function getLinkById()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Link')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['link_id' => ['required', 'integer']]);
        if (!empty($error)) {
            return $error;
        }

        $linkId = (int) $fields['link_id'];
        $payload = ['id' => $linkId];

        $link = BitCrmHelper::normalizeData(Link::findOne(['id' => $linkId]));

        if (empty($link)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('Link not found.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => $link];
    }
}
