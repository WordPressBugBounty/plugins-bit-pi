<?php

namespace BitApps\Pi\Services;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Model\FlowLog;

class LogService
{
    public static function save($logs)
    {
        if (!empty($logs)) {
            return FlowLog::insert($logs);
        }

        return false;
    }
}
