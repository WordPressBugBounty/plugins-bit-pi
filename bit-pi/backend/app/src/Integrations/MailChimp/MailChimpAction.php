<?php

namespace BitApps\Pi\src\Integrations\MailChimp;

if (!\defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Integrations\MailChimp\deprecated\MailChimpDeprecated;
use BitApps\Pi\src\Interfaces\ActionInterface;

class MailChimpAction implements ActionInterface
{
    private NodeInfoProvider $nodeInfoProvider;

    private MailChimpDeprecated $mailChimpDeprecated;

    private MailChimpService $mailChimpService;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        $executedNodeAction = $this->executeMailChimpAction();

        return Utility::formatResponseData(
            $executedNodeAction['status_code'],
            $executedNodeAction['payload'],
            $executedNodeAction['response']
        );
    }

    private function executeMachine($machineSlug, $audienceFieldMapData, $addressFieldMapData, $fieldMapData, $tagFieldMapData, $configs)
    {
        switch ($machineSlug) {
            case 'createSubscriber':
                return $this->mailChimpDeprecated->createSubscriber($audienceFieldMapData, $addressFieldMapData, $fieldMapData);

            case 'addUpdateMember':
                $fieldMapData = array_merge($fieldMapData, ['email_address' => $audienceFieldMapData['Email']], ['merge_fields' => $audienceFieldMapData]);

                if ($configs['address-field-switch']['value'] === true) {
                    $fieldMapData['merge_fields'] = array_merge($fieldMapData['merge_fields'], ['ADDRESS' => $addressFieldMapData]);
                }

                return $this->mailChimpService->addUpdateMember($fieldMapData['select-audience'], $audienceFieldMapData['Email'], $fieldMapData, $configs);

            case 'addRemoveMemberTag':
                return $this->mailChimpService->addRemoveMemberTag($fieldMapData['select-audience'], $fieldMapData['member-email'], $tagFieldMapData);

            case 'addMemberNote':
                return $this->mailChimpService->addMemberNote($fieldMapData['select-audience'], $fieldMapData['member-email'], $fieldMapData['note']);

            case 'deleteMemberFromList':
                return $this->mailChimpService->deleteMemberFromList($fieldMapData['select-audience'], $fieldMapData['member-email']);

            case 'getMemberFromList':
                return $this->mailChimpService->getMemberFromList($fieldMapData['select-audience'], $fieldMapData['member-email']);

            case 'getMembersFromList':
                return $this->mailChimpService->getMembersFromList($fieldMapData['select-audience'], $fieldMapData['status'], $fieldMapData['count']);
        }
    }

    private function executeMailChimpAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();
        $configs = $this->nodeInfoProvider->getFieldMapConfigs();
        $audienceFieldMapData = $this->nodeInfoProvider->getFieldMapRepeaters('field-map.value', false, true, 'mailchimpField', 'value');
        $addressFieldMapData = $this->nodeInfoProvider->getFieldMapRepeaters('address-field-map.value', false, true, 'mailchimpAddressField', 'value');
        $tagFieldMapData = $this->nodeInfoProvider->getFieldMapRepeaters('tag-field-map.value', false, false);
        $fieldMapData = $this->nodeInfoProvider->getFieldMapData();

        $dataCenter = $this->nodeInfoProvider->getData();
        $dataCenter = $dataCenter['db']['dataCenter'];

        $accessToken = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::OAUTH2,
            $configs['connection-id']
        )->getAccessToken();

        $httpClient = new HttpClient(
            [
                'headers' => [
                    'Authorization' => $accessToken,
                ],
            ]
        );

        $this->mailChimpDeprecated = new MailChimpDeprecated($httpClient, $dataCenter);

        $this->mailChimpService = new MailChimpService($httpClient, $dataCenter);

        return $this->executeMachine($machineSlug, $audienceFieldMapData, $addressFieldMapData, $fieldMapData, $tagFieldMapData, $configs);
    }
}
