<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Helpers\DateTimeHelper;
use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\Model\Flow;
use BitApps\Pi\Model\FlowNode;
use BitApps\Pi\src\Tools\Schedule\ScheduleTool;

final class HookListenerController
{
    /**
     * Get Hook response.
     *
     * @return collection Hook response
     */
    public const LISTENER_TIME_LIMIT = '+3 minutes ';

    public function captureResponse(Request $request)
    {
        $validated = $request->validate(
            [
                'flow_id'       => ['required', 'integer'],
                'node_id'       => ['required', 'string', 'sanitize:text'],
                'listener_type' => ['required', 'string', 'sanitize:text']
            ]
        );

        $flow = Flow::where('id', $validated['flow_id'])->first();

        if (!$flow) {
            return Response::error('Flow does not exist');
        }

        $nodeInfo = FlowNode::select(['machine_slug'])->where('node_id', $validated['node_id'])->first();

        if ($nodeInfo && $nodeInfo->machine_slug === 'schedule') {
            $flow->listener_type = Flow::LISTENER_TYPE['RUN_ONCE'];

            if (!$flow->save()) {
                return Response::error('Error updating flow');
            }

            (new ScheduleTool())->scheduleTriggerHandler($validated['node_id'], null, null, true);

            return Response::success('Schedule node detected, executing flow directly.');
        }

        $currentDateTime = (new DateTimeHelper())->getCurrentDateTime();

        if ($flow->listener_type === Flow::LISTENER_TYPE['NONE']) {
            $flow->listener_type = $validated['listener_type'] === 'CAPTURE' ? Flow::LISTENER_TYPE['CAPTURE'] : Flow::LISTENER_TYPE['RUN_ONCE'];

            $flow->settings = wp_json_encode(
                [
                    'capture_start_time' => $currentDateTime
                ],
                true
            );

            if (!$flow->save()) {
                return Response::error('Error updating flow');
            }
        }

        $flowSettings = JSON::maybeDecode($flow->settings, true);

        if (strtotime(self::LISTENER_TIME_LIMIT . $flowSettings['capture_start_time']) < strtotime($currentDateTime)) {
            if (!$this->updateListenerTypeStatus($validated['flow_id'])) {
                return Response::error('Error updating flow listener status');
            }

            return Response::code('WARNING')->success('3 minute listener time expired.');
        }

        if ($flow->is_hook_capture !== Flow::IS_HOOK_CAPTURED) {
            return Response::success(false);
        }

        if (!$this->updateListenerTypeStatus($validated['flow_id'])) {
            return Response::error('Error updating flow listener status');
        }

        return Response::success($this->getVariables($validated));
    }

    public function stopHookListener(Request $request)
    {
        $validated = $request->validate(
            [
                'flowId' => ['required', 'integer']
            ]
        );

        Flow::findOne(['id' => $validated['flowId']])
            ->update(
                [
                    'listener_type' => Flow::LISTENER_TYPE['NONE'],
                ]
            )->save();

        return Response::success('listener stopped');
    }

    private function updateListenerTypeStatus($flowId)
    {
        $flow = Flow::where('id', $flowId)->first();

        $flow->is_hook_capture = false;

        $flow->listener_type = Flow::LISTENER_TYPE['NONE'];

        return $flow->save();
    }

    private function getVariables($data)
    {
        $response = false;
        $LISTENER_TYPE = $data['listener_type'];

        $query = FlowNode::select('node_id', 'variables')->where('flow_id', $data['flow_id']);

        if ($LISTENER_TYPE === 'CAPTURE') {
            $response = $query->where('node_id', $data['node_id'])->first();
        } elseif ($LISTENER_TYPE === 'RUN_ONCE') {
            $response = $query->get();
        }

        return JSON::maybeDecode($response);
    }
}
