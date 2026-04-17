<?php

namespace BitApps\Pi\Services;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Model\Flow;
use BitApps\Pi\Model\FlowNode;
use Exception;
use Throwable;

class FlowTemplateImportService
{
    public function processImport($flowId, $data)
    {
        unset($data['id']);

        $newData = JSON::maybeDecode($this->replaceFlowIdNested($data, $flowId), true);

        $nodes = [];

        if (isset($newData['nodes'])) {
            $nodes = array_map(
                function ($node) use ($flowId) {
                    unset($node['id']);

                    $node['flow_id'] = $flowId;
                    $node['data'] = empty($node['data']) ? null : JSON::maybeEncode($node['data']);
                    $node['variables'] = empty($node['variables']) ? null : JSON::maybeEncode($node['variables']);
                    $node['field_mapping'] = empty($node['field_mapping']) ? null : JSON::maybeEncode($node['field_mapping']);
                    $node['app_slug'] = $node['app_slug'] ?? null;
                    $node['machine_slug'] = $node['machine_slug'] ?? null;
                    $node['node_id'] = $node['node_id'] ?? null;

                    return $node;
                },
                $newData['nodes']
            );
        }

        $newData['nodes'] = $nodes;

        $flowUpdate = Flow::findOne(['id' => $flowId]);
        $flowUpdate->update($newData)->save();
        $nodesDelete = FlowNode::where('flow_id', $flowId)->delete();
        $nodesInsert = true;

        if (!empty($newData['nodes'])) {
            $nodesInsert = FlowNode::insert($newData['nodes']);
        }

        return [
            'isImported'   => $flowUpdate && \is_array($nodesDelete) && $nodesInsert,
            'importedFlow' => $newData,
            'flowUpdate'   => $flowUpdate,
        ];
    }

    public function importFlow($flowId, $data)
    {
        Flow::startTransaction();
        FlowNode::startTransaction();

        try {
            $processImport = $this->processImport($flowId, $data);

            if (!$processImport['isImported']) {
                throw new Exception('Flow import failed.');
            }

            Flow::commit();
            FlowNode::commit();

            return true;
        } catch (Throwable $th) {
            Flow::rollback();
            FlowNode::rollback();

            return false;
        }
    }

    private function replaceFlowIdNested($data, $flowId)
    {
        if (empty($data)) {
            return;
        }

        return preg_replace_callback(
            '/("|\')(\d+-\d+|\d+-\d+-\d+)("|\')/',
            fn ($matches) => $this->replaceFlowId($matches[0], $flowId),
            JSON::maybeEncode($data)
        );
    }

    private function replaceFlowId($nodeId, $flowId)
    {
        return preg_replace('/\d+/', $flowId, $nodeId, 1);
    }
}
