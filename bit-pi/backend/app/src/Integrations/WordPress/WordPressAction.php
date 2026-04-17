<?php

namespace BitApps\Pi\src\Integrations\WordPress;

use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Integrations\WordPress\helpers\WordPressActionHandler;
use BitApps\Pi\src\Interfaces\ActionInterface;

if (!defined('ABSPATH')) {
    exit;
}


class WordPressAction implements ActionInterface
{
    private $nodeInfoProvider;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    public function execute(): array
    {
        return $this->executeWordPressAction() ?? [];
    }

    private function executeWordPressAction()
    {
        return WordPressActionHandler::executeAction($this->nodeInfoProvider);
    }
}
