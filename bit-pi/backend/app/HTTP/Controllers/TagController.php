<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


use BitApps\Pi\Deps\BitApps\WPKit\Helpers\Slug;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Request\Request;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\Model\Tag;
use BitApps\Pi\Rules\UniqueRule;

final class TagController
{
    public function index()
    {
        return Tag::get(['id', 'title', 'status']);
    }

    public function store(Request $request)
    {
        $validated = $request->validate(
            [
                'title'  => ['required', 'sanitize:text', new UniqueRule(Tag::class, 'title')],
                'filter' => ['nullable', 'string', 'sanitize:text']
            ]
        );

        $validated['slug'] = Slug::generate($validated['title']);

        $tag = Tag::insert($validated);

        if (!$tag) {
            return Response::error('Failed to save tag');
        }

        return Response::success($tag);
    }

    public function destroy(Request $request)
    {
        $getFlow = new Tag($request->tagId);
        $getFlow->delete();

        return Response::success('Tag deleted successfully');
    }

    public function update(Request $request)
    {
        $validated = $request->validate(
            [
                'id'    => ['required', 'integer'],
                'title' => ['required', 'sanitize:text', (new UniqueRule(Tag::class, 'title'))->ignore($request->id)],
            ]
        );

        $validated['slug'] = Slug::generate($validated['title']);

        $getFlow = Tag::findOne(['id' => $request->id]);
        $getFlow->update($validated);
        $getFlow->save();

        return Response::success($getFlow);
    }

    public function updateStatus(Request $request)
    {
        $validated = $request->validate(
            [
                'id'     => ['required', 'integer'],
                'status' => ['required', 'boolean']
            ]
        );

        $getFlow = Tag::findOne(['id' => $request->id]);
        $getFlow->update($validated);
        $getFlow->save();

        return Response::success('Tag status updated successfully');
    }
}
