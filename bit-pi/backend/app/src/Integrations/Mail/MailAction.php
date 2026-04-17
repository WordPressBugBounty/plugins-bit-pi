<?php

namespace BitApps\Pi\src\Integrations\Mail;

use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!defined('ABSPATH')) {
    exit;
}


class MailAction implements ActionInterface
{
    private $nodeInfoProvider;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        return $this->executeMailAction() ?? [];
    }

    private function executeMailAction()
    {
        $machineSlug = $this->nodeInfoProvider->getMachineSlug();

        $mailService = new MailServices($this->nodeInfoProvider);

        switch ($machineSlug) {
            case 'sendEmail':
                return $mailService->sendEmail();

                break;
        }
    }
}
