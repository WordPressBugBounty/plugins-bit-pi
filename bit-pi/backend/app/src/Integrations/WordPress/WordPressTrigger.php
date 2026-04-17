<?php

namespace BitApps\Pi\src\Integrations\WordPress;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Helpers\Node;
use BitApps\Pi\Services\FlowService;
use BitApps\Pi\src\Flow\FlowExecutor;
use BitApps\Pi\src\Flow\NodeInfoProvider;

class WordPressTrigger
{
    public static function postStatusUpdated($newStatus, $oldStatus, $post)
    {
        if ($newStatus === $oldStatus) {
            return;
        }

        $flows = FlowService::exists(WordPressTasks::getAppSlug(), 'postStatusUpdated');

        if (!$flows) {
            return;
        }

        foreach ($flows as $flow) {
            $triggerNode = Node::getNodeInfoById($flow->id . '-1', $flow->nodes);
            if (!$triggerNode) {
                continue;
            }

            // match submission
            $nodeHelper = new NodeInfoProvider($triggerNode);
            $configPostType = $nodeHelper->getFieldMapConfigs('post-type.value');
            $configPostId = $nodeHelper->getFieldMapConfigs('post-id.value');

            if (\is_array($configPostType) && \count($configPostType) && !\in_array($post->post_type, $configPostType)) {
                continue;
            }

            if (\is_array($configPostId) && \count($configPostId) && !\in_array($post->ID, $configPostId)) {
                continue;
            }

            FlowExecutor::execute($flow, ['new_status' => $newStatus, 'old_status' => $oldStatus, 'post' => $post]);
        }
    }

    public static function captureDoActionHookData($nodeId, $data)
    {
        $flow = FlowService::exists(WordPressTasks::getAppSlug(), 'doAction', ['*'], ['*'], '=');

        if (!$flow) {
            return;
        }

        FlowExecutor::execute($flow[0], $data);
    }
}
