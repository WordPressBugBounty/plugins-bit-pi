<?php

namespace BitApps\Pi\src\Integrations\ElasticMail;

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!\defined('ABSPATH')) {
    exit;
}


class ElasticMailAction implements ActionInterface
{
    public const BASE_URL = 'https://api.elasticemail.com/v4';

    private NodeInfoProvider $nodeInfoProvider;

    private ElasticMailContact $elasticMailContact;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeElasticMailAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeElasticMailAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();

        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');

        $listIds = $this->nodeInfoProvider->getFieldMapConfigs('list-ids.value');

        $statusId = $this->nodeInfoProvider->getFieldMapConfigs('status-id.value');

        $dataArr = $this->nodeInfoProvider->getFieldMapRepeaters('contact-fields.value', false, true, 'elasticMailField', 'value');

        $customFieldData = $this->nodeInfoProvider->getFieldMapRepeaters('custom-fields.value', false, true, 'elasticMailCustomField', 'value');

        $tokenAuthorization = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::API_KEY,
            $connectionId
        );

        $apiKey = $tokenAuthorization->getAccessToken();

        $header = [
            'X-ElasticEmail-ApiKey' => $apiKey,
            'Content-Type'          => 'application/json',
        ];

        $this->elasticMailContact = new ElasticMailContact(static::BASE_URL, $header);

        $listNamesForQueryParams = $this->generateListParams($listIds);

        if ($machineSlug === 'createContact') {
            $dataArr = $this->formattedData($customFieldData, $statusId, $dataArr);

            return $this->elasticMailContact->createNewContact($dataArr, $listNamesForQueryParams);
        }
    }

    private function formattedData($customFieldData, $statusId, $data = [])
    {
        $newData = [];

        foreach ($data as $key => $value) {
            $newData[$key] = $value;
        }

        if (\count($customFieldData) > 0) {
            $newData['CustomFields'] = $customFieldData;
        }

        if ($statusId) {
            $newData['Status'] = $statusId;
        }

        return $newData;
    }

    private function generateListParams($listIds)
    {
        $result = '';

        foreach ($listIds as $item) {
            $result .= 'listnames=' . urlencode($item) . '&';
        }

        return rtrim($result, '&');
    }
}
