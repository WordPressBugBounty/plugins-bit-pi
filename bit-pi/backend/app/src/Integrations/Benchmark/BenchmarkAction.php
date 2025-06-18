<?php

namespace BitApps\Pi\src\Integrations\Benchmark;

use BitApps\Pi\Helpers\MixInputHandler;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!\defined('ABSPATH')) {
    exit;
}


class BenchmarkAction implements ActionInterface
{
    public const BASE_URL = 'https://clientapi.benchmarkemail.com/';

    private NodeInfoProvider $nodeInfoProvider;

    private BenchmarkService $benchmarkService;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeBenchmarkAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeBenchmarkAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();

        $connectionId = $this->nodeInfoProvider->getFieldMapConfigs('connection-id.value');

        $listId = $this->nodeInfoProvider->getFieldMapConfigs('list-id.value');

        $dataArr = $this->nodeInfoProvider->getFieldMapRepeaters('contact-row.value', false, true, 'benchmarkField', 'value');

        $tokenAuthorization = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::API_KEY,
            $connectionId
        );

        $apiKey = $tokenAuthorization->getAccessToken();

        $header = [
            'AuthToken'    => $apiKey,
            'content-type' => 'application/json'
        ];

        $this->benchmarkService = new BenchmarkService(static::BASE_URL, $header);

        if ($machineSlug === 'createContact') {
            $dataArr = $this->formattedData($dataArr);

            return $this->benchmarkService->createNewContact($dataArr, $listId);
        }

        if ($machineSlug === 'updateContact') {
            $contactIdMixValue = $this->nodeInfoProvider->getFieldMapConfigs('contact-id.value');

            $contactId = MixInputHandler::replaceMixTagValue($contactIdMixValue);

            $dataArr = $this->formattedData($dataArr);

            return $this->benchmarkService->updateContact($dataArr, $listId, $contactId);
        }

        return [
            'response'    => null,
            'payload'     => null,
            'status_code' => 400
        ];
    }

    private function formattedData($data = [])
    {
        $data['EmailPerm'] = '1';

        return [
            'Data' => $data
        ];
    }
}
