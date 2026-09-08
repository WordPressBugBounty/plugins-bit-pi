<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\HTTP\Requests\CustomAppRequest;
use BitApps\Pi\Model\CustomApp;
use BitApps\Pi\Services\CustomAppService;

final class CustomAppController
{
    public function index()
    {
        $query = CustomApp::select(['id', 'name', 'slug', 'description', 'color', 'status'])
            ->selectRaw('CONCAT(%s, logo) AS logo', [Config::get('UPLOAD_BASE_URL')]);

        $query->with('customMachines');

        $customApps = $query->desc()->get();

        return Response::success($customApps);
    }

    public function getActiveCustomApps()
    {
        return CustomApp::select(['name', 'slug', 'color'])
            ->selectRaw('CONCAT(%s, logo) AS logo', [Config::get('UPLOAD_BASE_URL')])
            ->where('status', 1)
            ->get();
    }

    public function getActiveCustomAppsMeta()
    {
        $customAppTable = Config::withDBPrefix('custom_apps');
        $customMachineTable = Config::withDBPrefix('custom_machines');

        $query = "SELECT 
            a.name, a.slug, a.color, a.description,
            CONCAT('%s', a.logo) as logo, 
            b.name as machine_name,
            b.slug as machine_slug,
            b.app_type as machine_app_type,
            b.config as machine_config,
            b.trigger_type as machine_trigger_type
            FROM {$customAppTable} a join {$customMachineTable} b 
            on a.id = b.custom_app_id WHERE a.status = 1";

        $customAppsMeta = CustomApp::raw($query, [Config::get('UPLOAD_BASE_URL')]);

        // group by custom app slug
        $customApps = [];

        foreach ($customAppsMeta as $customApp) {
            $machineConfig = json_decode($customApp->machine_config, true);

            if (!isset($customApps[$customApp->slug])) {
                $customApps[$customApp->slug] = [
                    'name'        => $customApp->name,
                    'slug'        => $customApp->slug,
                    'color'       => $customApp->color,
                    'logo'        => $customApp->logo,
                    'description' => $customApp->description,
                    'actions'     => [],
                    'triggers'    => [],
                ];
            }

            if ($customApp->machine_app_type === 'action') {
                $customApps[$customApp->slug]['actions'][] = [
                    'label'       => $customApp->machine_name,
                    'description' => $machineConfig['description'] ?? '',
                    'machineSlug' => $customApp->machine_slug,
                    'runType'     => $customApp->machine_app_type,
                    'triggerType' => 'scheduled',
                ];
            }

            if ($customApp->machine_app_type === 'trigger') {
                $customApps[$customApp->slug]['triggers'][] = [
                    'label'       => $customApp->machine_name,
                    'description' => $machineConfig['description'] ?? '',
                    'machineSlug' => $customApp->machine_slug,
                    'runType'     => $customApp->machine_app_type,
                    'triggerType' => $customApp->machine_trigger_type,
                ];
            }
        }

        // flatten the array
        return array_values($customApps);
    }

    public function store(CustomAppRequest $request)
    {
        $validatedData = $request->validated();

        if (!empty($validatedData['logo'])) {
            $validatedData['logo'] = $this->extractPathFromUrl($validatedData['logo']);
        }

        $created = CustomAppService::create($validatedData);

        if (isset($created['error'])) {
            return Response::error(
                $created['error'] === 'insert' ? 'Failed to insert data.' : 'Failed to update slug.'
            );
        }

        $insertedData = $created['app']->getAttributes();

        $insertedData['slug'] = $created['slug'];

        if (!isset($insertedData['status'])) {
            $insertedData['status'] = 1;
        }

        if (!empty($validatedData['logo'])) {
            $insertedData['logo'] = Config::get('UPLOAD_BASE_URL') . $insertedData['logo'];
        }

        return Response::success($insertedData);
    }

    public function update(CustomAppRequest $request, CustomApp $customApp)
    {
        // update() on a row that is gone builds no WHERE clause and would rewrite the whole table.
        if (!$customApp->exists()) {
            return Response::error('Custom app not found.');
        }

        $validatedData = $request->validated();

        if (!empty($validatedData['logo'])) {
            $validatedData['logo'] = $this->extractPathFromUrl($validatedData['logo']);
        }

        $updated = $customApp->update($validatedData);

        if ($updated) {
            return Response::success('Data updated successfully.');
        }

        return Response::error('Failed to update data.');
    }

    public function view(Request $request)
    {
        $validatedData = $request->validate(
            [
                'custom_app_id' => ['required', 'integer'],
            ]
        );

        // TODO::This query must be change because customMachines not exist in free version

        $customMachines = CustomApp::with('customMachines')->findOne(['custom_app_id' => $validatedData['custom_app_id']]);

        return Response::success($customMachines);
    }

    public function destroy(CustomApp $customApp)
    {
        // Route binding fills in the key alone, so the row itself is read here.
        $customApp = CustomApp::findOne(['id' => $customApp->id]);

        if (!$customApp) {
            return Response::error('Custom app not found.');
        }

        $flowTitles = CustomAppService::findFlowTitlesBySlug('app_slug', $customApp->slug);

        if ($flowTitles) {
            return Response::error('This custom app is used in the following flows: ' . implode(', ', $flowTitles));
        }

        $customApp->delete();

        return Response::success($customApp->id);
    }

    public function updateStatus(Request $request, CustomApp $customApp)
    {
        $validatedData = $request->validate(
            [
                'status' => ['required', 'boolean'],
            ]
        );

        // Route binding fills in the key alone, so the row itself is read here.
        $customApp = CustomApp::findOne(['id' => $customApp->id]);

        if (!$customApp) {
            return Response::error('Custom app not found.');
        }

        if ($validatedData['status'] === false) {
            $flowTitles = CustomAppService::findFlowTitlesBySlug('app_slug', $customApp->slug);

            if ($flowTitles) {
                return Response::error('This custom app is used in the following flows: ' . implode(', ', $flowTitles));
            }
        }

        $customApp->status = $validatedData['status'];

        if (!$customApp->save()) {
            return Response::error('Failed to update status.');
        }

        return Response::success('Status updated successfully.');
    }

    private function extractPathFromUrl($url)
    {
        $needle = Config::get('UPLOAD_BASE_URL');

        $position = strpos($url, $needle);

        if ($position !== false) {
            return substr($url, $position + \strlen($needle));
        }
    }
}
