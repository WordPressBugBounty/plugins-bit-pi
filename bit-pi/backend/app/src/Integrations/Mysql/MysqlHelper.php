<?php

namespace BitApps\Pi\src\Integrations\Mysql;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use Throwable;

/**
 * Internal route handlers that introspect the connected MySQL schema to populate
 * the table/column dropdowns in the flow builder.
 */
class MysqlHelper
{
    public static function getTables(Request $request): Response
    {
        $validated = $request->validate(
            [
                'connectionId' => ['required'],
            ]
        );

        try {
            $service = MysqlService::fromConnection($validated['connectionId']);

            return Response::success($service->listTables());
        } catch (Throwable $th) {
            return Response::error(['message' => $th->getMessage()]);
        }
    }

    public static function getColumns(Request $request): Response
    {
        $validated = $request->validate(
            [
                'connectionId' => ['required'],
                'table'        => ['required', 'string', 'sanitize:text'],
            ]
        );

        try {
            $service = MysqlService::fromConnection($validated['connectionId']);

            return Response::success($service->listColumns($validated['table']));
        } catch (Throwable $th) {
            return Response::error(['message' => $th->getMessage()]);
        }
    }
}
