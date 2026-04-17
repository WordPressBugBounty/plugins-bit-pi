<?php

namespace BitApps\Pi\src\Log;

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;

if (!defined('ABSPATH')) {
    exit;
}


class LogHandler
{
    private static $instance;

    private $logs = [];

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function addLog(
        $flowHistoryId,
        $nodeId,
        $status,
        $input,
        $output,
        $messages = null,
        $details = []
    ) {
        if (!$flowHistoryId || !$nodeId) {
            return;
        }

        $this->logs[] = [
            'flow_history_id' => $flowHistoryId,
            'node_id'         => $nodeId,
            'status'          => $status,
            'input'           => JSON::maybeEncode($input),
            'output'          => JSON::maybeEncode($output),
            'messages'        => $messages,
            'details'         => JSON::encode($details),
        ];
    }

    public static function getLogs()
    {
        return self::getInstance()->logs;
    }

    public static function destroy()
    {
        self::$instance = null;
    }
}
