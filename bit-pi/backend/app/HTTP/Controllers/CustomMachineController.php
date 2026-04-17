<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Helpers\Slug;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\HTTP\Requests\CustomMachineRequest;
use BitApps\Pi\Model\CustomMachine;
use BitApps\Pi\Services\CustomAppService;

final class CustomMachineController
{
    public function index(Request $request)
    {
        $validatedData = $request->validate(
            [
                'custom_app_id' => ['required', 'integer'],
            ]
        );

        $customMachines = CustomMachine::where('custom_app_id', $validatedData['custom_app_id'])->get();

        return Response::success($customMachines);
    }

    public function store(CustomMachineRequest $request)
    {
        $validatedData = $request->validated();

        $validatedData['config'] = JSON::maybeEncode($validatedData['config']);

        $insertedData = CustomMachine::insert($validatedData);

        if (!$insertedData) {
            return Response::error('Failed to insert data.');
        }

        $query = CustomMachine::findOne(['id' => $insertedData['id']])->update(['slug' => Slug::generate($validatedData['name']) . '-' . $insertedData['id']]);

        if (!$query->save()) {
            return Response::error('Failed to update slug.');
        }

        return Response::success($insertedData);
    }

    public function update(CustomMachineRequest $request, CustomMachine $customMachine)
    {
        $validatedData = $request->validated();

        $validatedData['config'] = JSON::maybeEncode($validatedData['config']);

        $updated = $customMachine->update($validatedData)->save();

        if ($updated) {
            return Response::success($updated);
        }

        return Response::error('Failed to update data.');
    }

    public function destroy(CustomMachine $customMachine)
    {
        $flowTitles = CustomAppService::findFlowTitlesBySlug('machine_slug', $customMachine->slug);

        if ($flowTitles) {
            return Response::error('This custom app module is used in the following flows: ' . implode(', ', $flowTitles));
        }

        $customMachine->delete();

        return Response::success($customMachine->id);
    }

    public function updateStatus(Request $request, CustomMachine $customMachine)
    {
        $flowTitles = CustomAppService::findFlowTitlesBySlug('machine_slug', $customMachine->slug);

        if ($flowTitles) {
            return Response::error('This custom app module is used in the following flows: ' . implode(', ', $flowTitles));
        }

        $validatedData = $request->validate(
            [
                'status' => ['required', 'boolean'],
            ]
        );

        $customMachine->status = $validatedData['status'];

        if (!$customMachine->save()) {
            return Response::error('Failed to update status.');
        }

        return Response::success('Status updated successfully.');
    }

    public function machineByAppSlugAndMachineSlug(Request $request)
    {
        $validatedData = $request->validate(
            [
                'appSlug'     => ['required', 'string', 'sanitize:text'],
                'machineSlug' => ['nullable', 'string', 'sanitize:text'],
            ]
        );

        $customAppsTable = Config::withDBPrefix('custom_apps');
        $customMachinesTable = Config::withDBPrefix('custom_machines');

        $query = CustomMachine::leftJoin('custom_apps', "{$customAppsTable}.id", '=', "{$customMachinesTable}.custom_app_id")
            ->select(
                [
                    "{$customAppsTable}.slug as app_slug",
                    "{$customMachinesTable}.connection_id",
                    "{$customMachinesTable}.name",
                    "{$customMachinesTable}.slug",
                    "{$customMachinesTable}.app_type",
                    "{$customMachinesTable}.trigger_type",
                    "{$customMachinesTable}.config",
                ]
            )
            ->where("{$customAppsTable}.slug", $validatedData['appSlug'])
            ->where("{$customMachinesTable}.status", 1);

        $customMachines = isset($validatedData['machineSlug'])
            ? $query->where("{$customMachinesTable}.slug", $validatedData['machineSlug'])->first()
            : $query->get();

        if (!$customMachines) {
            return Response::error('No custom machine found for this app.');
        }

        return Response::success($customMachines);
    }
}
