<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\Services\SystemInfo;

final class SystemInfoController
{
    public function index()
    {
        return Response::success(SystemInfo::get());
    }
}
