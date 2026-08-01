<?php

namespace BitApps\Pi\src\Integrations\BitCrm\Services;

use BitApps\Crm\Deps\BitApps\WPKit\Helpers\Slug;
use BitApps\Crm\Model\Tag;
use BitApps\Crm\Model\TagEntity;
use BitApps\Pi\Deps\BitApps\WPKit\Hooks\Hooks;
use BitApps\Pi\src\Integrations\BitCrm\BitCrmHelper;
use BitApps\Pi\src\Integrations\IntegrationHelper;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

final class TagService extends BaseService
{
    public function createTag()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Services\TagService')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap(
            $fields,
            [
                'title'  => ['required', 'string'],
                'module' => ['required', 'string'],
            ]
        );
        if (!empty($error)) {
            return $error;
        }

        $payload = [
            'title'  => $fields['title'],
            'module' => $fields['module'],
        ];

        $result = (new \BitApps\Crm\Services\TagService())->store($payload);

        if ($result === false || (\is_array($result) && ($result['success'] ?? true) === false)) {
            $reason = \is_array($result) ? ($result['errors'] ?? null) : null;

            return ['status_code' => 400, 'payload' => $payload, 'response' => $reason ?? __('Failed to create tag. The module may be invalid or the tag already exists.', 'bit-pi')];
        }

        $tag = \is_array($result) && \array_key_exists('data', $result) ? $result['data'] : $result;

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData($tag)];
    }

    public function updateTag()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Tag')) {
            return $error;
        }

        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Deps\BitApps\WPKit\Helpers\Slug')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap(
            $fields,
            [
                'tag_id' => ['required', 'integer'],
                'title'  => ['required', 'string'],
            ]
        );
        if (!empty($error)) {
            return $error;
        }

        $tagId = (int) $fields['tag_id'];
        $payload = ['id' => $tagId, 'title' => $fields['title']];

        $tag = Tag::findOne(['id' => $tagId]);
        if (empty($tag)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('Tag not found.', 'bit-pi')];
        }

        // Bit CRM regenerates the slug from the title on every tag update.
        $updateData = [
            'title'      => $fields['title'],
            'slug'       => Slug::generate($fields['title']),
            'updated_by' => get_current_user_id(),
        ];

        if (!empty($fields['module'])) {
            $updateData['module'] = $fields['module'];
            $payload['module'] = $fields['module'];
        }

        if (!$tag->update($updateData)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('Failed to update tag.', 'bit-pi')];
        }

        Hooks::doAction('bit_crm/tag_updated', $tag);

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData($tag)];
    }

    public function deleteTag()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Tag')) {
            return $error;
        }

        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\TagEntity')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['tag_id' => ['required']]);
        if (!empty($error)) {
            return $error;
        }

        $tagIds = BitCrmHelper::toIntArray($fields['tag_id']);
        $payload = ['ids' => $tagIds];

        if (empty($tagIds)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('A valid tag id is required.', 'bit-pi')];
        }

        $tags = Tag::whereIn('id', $tagIds);

        if (!$tags->count()) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('Tag not found.', 'bit-pi')];
        }

        try {
            $tags->delete();

            // The tags are gone, so drop their entity relations too, as Bit CRM does.
            TagEntity::whereIn('tag_id', $tagIds)->delete();
        } catch (Throwable $th) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => $th->getMessage()];
        }

        Hooks::doAction('bit_crm/tag_deleted', $tagIds);

        return ['status_code' => 200, 'payload' => $payload, 'response' => ['ids' => $tagIds]];
    }

    public function getAllTags()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Tag')) {
            return $error;
        }

        $fields = $this->fields();
        $module = $fields['module'] ?? '';

        $tags = empty($module) ? Tag::all() : Tag::where('module', $module)->get();

        return ['status_code' => 200, 'payload' => ['module' => $module], 'response' => $tags ? $tags->toArray() : []];
    }

    public function getTagByTitle()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Tag')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['title' => ['required', 'string']]);
        if (!empty($error)) {
            return $error;
        }

        $title = $fields['title'];
        $module = $fields['module'] ?? '';
        $payload = ['title' => $title, 'module' => $module];

        // Titles repeat across modules, so narrow by module when one is given.
        $conditions = ['title' => $title];
        if (!empty($module)) {
            $conditions['module'] = $module;
        }

        $tag = BitCrmHelper::normalizeData(Tag::findOne($conditions));

        if (empty($tag['id'])) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('No tag found with this title.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => $tag];
    }

    public function getTagById()
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Tag')) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['tag_id' => ['required', 'integer']]);
        if (!empty($error)) {
            return $error;
        }

        $tagId = (int) $fields['tag_id'];
        $payload = ['id' => $tagId];

        $tag = BitCrmHelper::normalizeData(Tag::findOne(['id' => $tagId]));

        if (empty($tag)) {
            return ['status_code' => 400, 'payload' => $payload, 'response' => __('Tag not found.', 'bit-pi')];
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => $tag];
    }
}
