<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\Helpers\Hash;
use BitApps\Pi\HTTP\Requests\ConnectionIndexRequest;
use BitApps\Pi\HTTP\Requests\ConnectionStoreRequest;
use BitApps\Pi\Model\Connection;

final class ConnectionController
{
    public function index(ConnectionIndexRequest $request)
    {
        $query = Connection::where('status', Connection::STATUS['verified']);

        if (!empty($request['appSlug'])) {
            $query->where('app_slug', $request['appSlug']);
        }

        $connections = $query->desc()->get(['id', 'app_slug', 'auth_type', 'connection_name', 'auth_details', 'created_at']);

        return Response::success($connections);
    }

    public function store(ConnectionStoreRequest $request)
    {
        $reqData = $request->validated();

        $encrypt_keys = $request->get('encrypt_keys', []);
        $reqData['encrypt_keys'] = implode(',', $encrypt_keys);
        $authDetails = $reqData['auth_details'];
        $authDetails['generated_at'] = time();

        if (\count($encrypt_keys) > 0) {
            foreach ($encrypt_keys as $value) {
                if (empty($authDetails[$value])) {
                    continue;
                }

                $authDetails[$value] = Hash::encrypt($authDetails[$value]);
            }
        }

        $reqData['auth_details'] = JSON::maybeEncode($authDetails);

        $connection = Connection::insert($reqData);

        return Response::success($connection);
    }

    public function update(Request $request, Connection $connection)
    {
        $validated = $request->validate(
            [
                'connection_name' => ['required', 'string', 'sanitize:text'],
            ]
        );

        $connection->update($validated)->save();

        return Response::success($connection);
    }

    public function destroy(Connection $connection)
    {
        $connection->delete();

        return Response::success($connection->id);
    }
}
