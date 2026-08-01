<?php

namespace BitApps\Pi\src\Integrations\BitCrm\Services;

use BitApps\Crm\Model\Activity;
use BitApps\Pi\Deps\BitApps\WPKit\Hooks\Hooks;
use BitApps\Pi\src\Integrations\BitCrm\BitCrmHelper;
use BitApps\Pi\src\Integrations\IntegrationHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bit CRM stores tasks, meetings and calls in one activities table and tells
 * them apart by `type`, so every action here is a thin type-bound wrapper over
 * a shared implementation.
 */
final class ActivityService extends BaseService
{
    public function createTask()
    {
        return $this->storeActivity('task');
    }

    public function createMeeting()
    {
        return $this->storeActivity('meeting');
    }

    public function createCall()
    {
        return $this->storeActivity('call');
    }

    public function updateTask()
    {
        return $this->modifyActivity('task');
    }

    public function updateMeeting()
    {
        return $this->modifyActivity('meeting');
    }

    public function updateCall()
    {
        return $this->modifyActivity('call');
    }

    public function updateTaskStatus()
    {
        return $this->changeActivityStatus('task');
    }

    public function updateMeetingStatus()
    {
        return $this->changeActivityStatus('meeting');
    }

    public function updateCallStatus()
    {
        return $this->changeActivityStatus('call');
    }

    public function deleteTask()
    {
        return $this->removeActivity('task');
    }

    public function deleteMeeting()
    {
        return $this->removeActivity('meeting');
    }

    public function deleteCall()
    {
        return $this->removeActivity('call');
    }

    public function getAllTasks()
    {
        return $this->listActivities('task');
    }

    public function getAllMeetings()
    {
        return $this->listActivities('meeting');
    }

    public function getAllCalls()
    {
        return $this->listActivities('call');
    }

    public function getTaskById()
    {
        return $this->showActivity('task');
    }

    public function getMeetingById()
    {
        return $this->showActivity('meeting');
    }

    public function getCallById()
    {
        return $this->showActivity('call');
    }

    private function storeActivity($type)
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Activity')) {
            return $error;
        }

        $fields = $this->fields();

        $rules = [
            'title'       => ['required', 'string'],
            'entity_id'   => ['required', 'integer'],
            'module'      => ['required', 'string'],
            'assigned_to' => ['required', 'integer'],
        ];

        // Bit CRM itself only requires a priority on tasks.
        if ($type === 'task') {
            $rules['priority'] = ['required', 'string'];
        }

        $error = IntegrationHelper::validateFieldMap($fields, $rules);
        if (!empty($error)) {
            return $error;
        }

        $payload = [
            'title'       => $fields['title'],
            'type'        => $type,
            'due_date'    => $fields['due_date'] ?? '',
            'details'     => $fields['details'] ?? '',
            'entity_id'   => (int) $fields['entity_id'],
            'module'      => $fields['module'],
            'assigned_to' => (int) $fields['assigned_to'],
            'is_shared'   => !empty($fields['is_shared']),
        ];

        if (!empty($fields['priority'])) {
            $payload['priority'] = $fields['priority'];
        }

        $payload['created_by'] = get_current_user_id();

        $activity = Activity::insert($payload);
        if (!$activity) {
            return [
                'status_code' => 400,
                'payload'     => $payload,
                'response'    => \sprintf(
                    // translators: %s: activity type (task, meeting or call)
                    __('Failed to create %s.', 'bit-pi'),
                    $type
                ),
            ];
        }

        Hooks::doAction('bit_crm/activity_created', $activity);

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData($activity)];
    }

    /**
     * Apply the supplied fields to an existing activity of the given type.
     *
     * Only fields that carry a value are written, so a flow can update a single
     * column without blanking the rest of the record.
     *
     * @param string $type one of task|meeting|call
     *
     * @return array{status_code: int, payload: array, response: mixed}
     */
    private function modifyActivity($type)
    {
        [$activity, $payload, $error] = $this->resolveActivity($type);
        if ($error !== null) {
            return $error;
        }

        $fields = $this->fields();
        $updateData = ['updated_by' => get_current_user_id()];

        foreach (['title', 'priority', 'due_date', 'details', 'module'] as $field) {
            if (isset($fields[$field]) && $fields[$field] !== '') {
                $updateData[$field] = $fields[$field];
            }
        }

        foreach (['entity_id', 'assigned_to'] as $field) {
            if (!empty($fields[$field])) {
                $updateData[$field] = (int) $fields[$field];
            }
        }

        if (isset($fields['is_shared']) && $fields['is_shared'] !== '') {
            $updateData['is_shared'] = !empty($fields['is_shared']);
        }

        $payload = array_merge($payload, $updateData);

        if (!$activity->update($updateData)) {
            return [
                'status_code' => 400,
                'payload'     => $payload,
                'response'    => \sprintf(
                    // translators: %s: activity type (task, meeting or call)
                    __('Failed to update %s.', 'bit-pi'),
                    $type
                ),
            ];
        }

        Hooks::doAction('bit_crm/activity_updated', $activity);

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData($activity)];
    }

    private function changeActivityStatus($type)
    {
        [$activity, $payload, $error] = $this->resolveActivity($type);
        if ($error !== null) {
            return $error;
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['status' => ['required', 'string']]);
        if (!empty($error)) {
            return $error;
        }

        $newStatus = $fields['status'] === 'completed' ? 'completed' : 'pending';
        $oldStatus = $activity->is_completed ? 'completed' : 'pending';

        $payload = array_merge($payload, ['new_status' => $newStatus, 'old_status' => $oldStatus]);

        if ($newStatus === $oldStatus) {
            return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData($activity)];
        }

        $updateData = [
            'is_completed' => $newStatus === 'completed',
            'updated_by'   => get_current_user_id(),
        ];

        if (!$activity->update($updateData)) {
            return [
                'status_code' => 400,
                'payload'     => $payload,
                'response'    => \sprintf(
                    // translators: %s: activity type (task, meeting or call)
                    __('Failed to update %s status.', 'bit-pi'),
                    $type
                ),
            ];
        }

        Hooks::doAction('bit_crm/activity_status_updated', $activity, $newStatus, $oldStatus);

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData($activity)];
    }

    private function removeActivity($type)
    {
        [$activity, $payload, $error] = $this->resolveActivity($type);
        if ($error !== null) {
            return $error;
        }

        $deletedActivity = BitCrmHelper::normalizeData($activity);

        if (!$activity->delete()) {
            return [
                'status_code' => 400,
                'payload'     => $payload,
                'response'    => \sprintf(
                    // translators: %s: activity type (task, meeting or call)
                    __('Failed to delete %s.', 'bit-pi'),
                    $type
                ),
            ];
        }

        Hooks::doAction('bit_crm/activity_deleted', $payload['id']);

        return ['status_code' => 200, 'payload' => $payload, 'response' => $deletedActivity];
    }

    private function listActivities($type)
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Activity')) {
            return $error;
        }

        $fields = $this->fields();
        $module = $fields['module'] ?? '';
        $entityId = (int) ($fields['entity_id'] ?? 0);

        $query = Activity::where('type', $type);

        if (!empty($module)) {
            $query = $query->where('module', $module);

            if (!empty($entityId)) {
                $query = $query->where('entity_id', $entityId);
            }
        }

        $activities = $query->get();

        return [
            'status_code' => 200,
            'payload'     => ['type' => $type, 'module' => $module, 'entity_id' => $entityId],
            'response'    => $activities ? $activities->toArray() : [],
        ];
    }

    private function showActivity($type)
    {
        [$activity, $payload, $error] = $this->resolveActivity($type);
        if ($error !== null) {
            return $error;
        }

        return ['status_code' => 200, 'payload' => $payload, 'response' => BitCrmHelper::normalizeData($activity)];
    }

    private function resolveActivity($type)
    {
        if ($error = BitCrmHelper::validateClassExists('BitApps\Crm\Model\Activity')) {
            return [null, [], $error];
        }

        $fields = $this->fields();

        $error = IntegrationHelper::validateFieldMap($fields, ['activity_id' => ['required', 'integer']]);
        if (!empty($error)) {
            return [null, [], $error];
        }

        $activityId = (int) $fields['activity_id'];
        $payload = ['id' => $activityId, 'type' => $type];

        $activity = Activity::findOne(['id' => $activityId]);

        if (empty($activity) || $activity->type !== $type) {
            return [
                null,
                $payload,
                [
                    'status_code' => 400,
                    'payload'     => $payload,
                    'response'    => \sprintf(
                        // translators: %s: activity type (task, meeting or call)
                        __('No %s found with this id.', 'bit-pi'),
                        $type
                    ),
                ],
            ];
        }

        return [$activity, $payload, null];
    }
}
