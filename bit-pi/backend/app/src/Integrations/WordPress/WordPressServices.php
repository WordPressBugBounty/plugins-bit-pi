<?php

namespace BitApps\Pi\src\Integrations\WordPress;

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Integrations\WordPress\helpers\WordPressActionHelper;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}


final class WordPressServices
{
    private $nodeInfoProvider;

    public function __construct(NodeInfoProvider $nodeInfoProvider)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
    }

    /**
     * Create WordPress User.
     *
     * @return collection
     */
    public function createUser()
    {
        $fields = $this->nodeInfoProvider->getFieldMapData();

        $userFields = $this->nodeInfoProvider->getFieldMapRepeaters('field-map.value', false, true, 'wordPressField', 'value');
        $autoPassword = $this->nodeInfoProvider->getFieldMapConfigs('is-auto-password-enabled.value') ?? false;
        $addMeta = $this->nodeInfoProvider->getFieldMapConfigs('show-user-meta-field.value');
        $userMeta = $this->nodeInfoProvider->getFieldMapRepeaters('user-meta-field-map.value', false, true, 'meta_key', 'value');

        $emailNotify = $fields['emailNotification'] ?? '';
        $userRole = $fields['userRole'] ?? '';
        $password = $autoPassword ? wp_generate_password() : ($fields['password'] ?? '');


        $payload = [
            'field_map'                       => $userFields,
            'user_meta'                       => $userMeta,
            'generate_password_automatically' => $autoPassword,
            'add_user_meta'                   => $addMeta,
            'email_notification'              => $emailNotify,
        ];

        if ($validation = WordPressActionHelper::validateUserCreateInput($userFields, $payload)) {
            return $validation;
        }

        if (empty($password)) {
            return WordPressActionHelper::response(__('Password is required', 'bit-pi'), $payload, 422);
        }

        if (empty($userRole)) {
            return WordPressActionHelper::response(__('User Role is required', 'bit-pi'), $payload, 422);
        }

        $userData = WordPressActionHelper::mapUserFields($userFields);
        $userData['user_pass'] = $password;
        $userData['role'] = $userRole;

        $userId = wp_insert_user($userData);
        $payload['field_map'] = $userData;

        if (is_wp_error($userId)) {
            return WordPressActionHelper::response(__('Failed to create user.', 'bit-pi'), $payload, 400);
        }

        $this->addUserMeta($userId, $addMeta, $userMeta);

        $this->sendEmailNotification($userId, $emailNotify);

        return WordPressActionHelper::response(['user_id' => $userId], $payload);
    }

    /**
     * Update WordPress User.
     *
     * @return collection
     */
    public function updateUser()
    {
        $fields = $this->nodeInfoProvider->getFieldMapData();
        $userFields = $this->nodeInfoProvider->getFieldMapRepeaters('field-map.value', false, true, 'wordPressField', 'value');
        $userMeta = $this->nodeInfoProvider->getFieldMapRepeaters('user-meta-field-map.value', false, true, 'meta_key', 'value');
        $addMeta = $this->nodeInfoProvider->getFieldMapConfigs('show-user-meta-field.value');
        $userId = $fields['userId'] ?? '';

        $payload = [
            'user_id'       => $userId,
            'field_map'     => $userFields,
            'add_user_meta' => $addMeta,
            'user_metadata' => $userMeta
        ];

        if (empty($userId)) {
            return WordPressActionHelper::response(__('User id is required', 'bit-pi'), $payload, 422);
        }

        $existingUser = get_user_by('ID', $userId);

        if (!$existingUser) {
            return WordPressActionHelper::response(__('User not found with provided ID', 'bit-pi'), $payload, 400);
        }

        $userData = WordPressActionHelper::mapUserFields($userFields);
        $userData['ID'] = $userId;

        if (!empty($fields['userRole'])) {
            $userData['role'] = $fields['userRole'];
        }

        $userId = wp_update_user($userData);
        $payload['field_map'] = $userData;

        if (is_wp_error($userId)) {
            return WordPressActionHelper::response(__('Failed to update user.', 'bit-pi'), $payload, 400);
        }

        $this->addUserMeta($userId, $addMeta, $userMeta);

        return WordPressActionHelper::response(__('User updated successfully', 'bit-pi'), $payload);
    }

    /**
     * Delete WordPress User.
     *
     * @return collection
     */
    public function deleteUser()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $switchByEmail = $this->nodeInfoProvider->getFieldMapConfigs('switch-email-mapping.value') ?? false;
        $switchByCustomReassignId = $this->nodeInfoProvider->getFieldMapConfigs('switch-reassign-id.value') ?? false;

        $userId = $data['userId'] ?? null;
        $userEmail = $data['userEmail'] ?? null;
        $reassignUserId = $data['reassignUser'] ?? null;
        $customReassignId = $data['customReassignId'] ?? null;
        $reassignId = $switchByCustomReassignId ? $customReassignId : $reassignUserId;

        $payload = [
            'user_id'              => $userId,
            'user_email'           => $userEmail,
            'reassign_id'          => $reassignUserId,
            'custom_reassign_id'   => $customReassignId,
            'switch_email_mapping' => $switchByEmail,
            'switch_reassign_id'   => $switchByCustomReassignId,
        ];

        if (!$switchByEmail && empty($userId)) {
            return WordPressActionHelper::response(__('User ID is required', 'bit-pi'), $payload, 422);
        }

        if ($switchByEmail) {
            if (empty($userEmail)) {
                return WordPressActionHelper::response(__('User email is required', 'bit-pi'), $payload, 422);
            }

            $user = get_user_by('email', $userEmail);
            if (!$user) {
                return WordPressActionHelper::response(__('User not found with provided email', 'bit-pi'), $payload, 400);
            }

            $userId = $user->ID;
            $payload['user_id'] = $userId;
        }

        wp_delete_user($userId, $reassignId);

        return WordPressActionHelper::response(__('User deleted successfully', 'bit-pi'), $payload);
    }

    /**
     * Get all wordPress users.
     *
     * @return collection
     */
    public function getAllUsers()
    {
        $allUsers = Utility::getUsers();

        return WordPressActionHelper::response($allUsers);
    }

    /**
     * Get WordPress User by Id.
     *
     * @return collection
     */
    public function getUserById()
    {
        $userId = $this->nodeInfoProvider->getFieldMapData()['userId'] ?? null;
        $payload = ['user_id' => $userId];

        if (!$userId) {
            return WordPressActionHelper::response(__('User id is required', 'bit-pi'), $payload, 422);
        }

        $user = Utility::getUserInfo($userId);

        if (empty($user)) {
            return WordPressActionHelper::response(__('User not found with provided id', 'bit-pi'), $payload, 400);
        }

        return WordPressActionHelper::response($user, $payload);
    }

    /**
     * Get WordPress User by Email.
     *
     * @return collection
     */
    public function getUserByEmail()
    {
        $userEmail = $this->nodeInfoProvider->getFieldMapData()['userEmail'] ?? null;
        $payload = ['user_email' => $userEmail];

        if (!$userEmail) {
            return WordPressActionHelper::response(__('User Email Address is required', 'bit-pi'), $payload, 422);
        }

        $user = Utility::getUserDataByField('email', $userEmail);

        if (empty($user)) {
            return WordPressActionHelper::response(__('User not found with provided email', 'bit-pi'), $payload, 400);
        }

        return WordPressActionHelper::response($user, $payload);
    }

    /**
     * Get WordPress User by Field.
     *
     * @return collection
     */
    public function getUserByField()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $fieldKey = $data['fieldKey'] ?? null;
        $fieldValue = $data['fieldValue'] ?? null;
        $payload = [
            'field_key'   => $fieldKey,
            'field_value' => $fieldValue
        ];

        if (empty($fieldKey)) {
            return WordPressActionHelper::response(__('Field is required', 'bit-pi'), $payload, 422);
        }

        if (empty($fieldValue)) {
            return WordPressActionHelper::response(__('Field Value is required', 'bit-pi'), $payload, 422);
        }


        $user = Utility::getUserDataByField($fieldKey, $fieldValue);

        if (empty($user)) {
            return WordPressActionHelper::response(__('User not found with provided field & value', 'bit-pi'), $payload, 400);
        }

        return WordPressActionHelper::response($user, $payload);
    }

    /**
     * Get All WordPress Users by Role.
     *
     * @return collection
     */
    public function getAllUsersByRole()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $switchByCustomRole = $this->nodeInfoProvider->getFieldMapConfigs('switch-user-role.value') ?? false;

        $userRole = $data['userRole'] ?? null;
        $customUserRole = $data['customUserRole'] ?? null;

        $payload = [
            'user_role'        => $userRole,
            'custom_user_role' => $customUserRole,
            'switch_user_role' => $switchByCustomRole
        ];

        $finalRole = $switchByCustomRole ? $customUserRole : $userRole;

        if (empty($finalRole)) {
            $errorMsg = $switchByCustomRole
                ? __('Custom User Role is required', 'bit-pi')
                : __('User Role is required', 'bit-pi');

            return WordPressActionHelper::response($errorMsg, $payload, 422);
        }

        $users = Utility::getUsers(['role' => $finalRole, 'orderby' => 'ID']);

        return WordPressActionHelper::response($users, $payload);
    }

    /**
     * Get WordPress User Metadata.
     *
     * @return collection
     */
    public function getUserMetadata()
    {
        $userId = $this->nodeInfoProvider->getFieldMapData()['userId'] ?? null;
        $payload = ['user_id' => $userId];

        if (!$userId) {
            return WordPressActionHelper::response(__('User id is required', 'bit-pi'), $payload, 422);
        }

        $metadata = Utility::getUserMetadata($userId);

        if (empty($metadata)) {
            return WordPressActionHelper::response(__('User Metadata not found with provided id', 'bit-pi'), $payload, 400);
        }

        return WordPressActionHelper::response($metadata, $payload);
    }

    /**
     * Get WordPress User Metadata.
     *
     * @return collection
     */
    public function getUserMetadataByMetaKey()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $userId = $data['userId'] ?? null;
        $metaKey = $data['metaKey'] ?? null;
        $payload = [
            'user_id'  => $userId,
            'meta_key' => $metaKey // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Data payload, not a direct query
        ];

        if (empty($userId)) {
            return WordPressActionHelper::response(__('User id is required', 'bit-pi'), $payload, 422);
        }

        if (empty($metaKey)) {
            return WordPressActionHelper::response(__('Meta Key Value is required', 'bit-pi'), $payload, 422);
        }

        $metadata = Utility::getUserMetadata($userId, $metaKey, true);

        if (empty($metadata)) {
            return WordPressActionHelper::response(__('User Metadata not found with provided id and meta key', 'bit-pi'), $payload, 400);
        }

        return WordPressActionHelper::response($metadata, $payload);
    }

    /**
     * Update WordPress User Metadata.
     *
     * @return collection
     */
    public function updateUserMetadata()
    {
        $userId = $this->nodeInfoProvider->getFieldMapData()['userId'] ?? null;
        $metaFields = $this->nodeInfoProvider->getFieldMapRepeaters('field-map.value', false, true, 'meta_key', 'value');

        $payload = [
            'user_id'   => $userId,
            'field_map' => $metaFields
        ];

        if (empty($userId)) {
            return WordPressActionHelper::response(__('User id is required', 'bit-pi'), $payload, 422);
        }

        if (!get_user_by('ID', $userId)) {
            return WordPressActionHelper::response(__('User not found with provided ID', 'bit-pi'), $payload, 400);
        }

        $this->addUserMeta($userId, true, $metaFields);

        return WordPressActionHelper::response(get_user_meta($userId), $payload);
    }

    /**
     * Create WordPress User Role.
     *
     * @return collection
     */
    public function createRole()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $roleName = $data['roleName'] ?? null;
        $displayName = $data['roleDisplayName'] ?? null;
        $capabilities = Utility::convertStringToArray($data['roleCapabilities']);

        $payload = [
            'role_name'         => $roleName,
            'role_display_name' => $displayName,
            'role_capabilities' => $capabilities
        ];

        if (empty($roleName)) {
            return WordPressActionHelper::response(__('Role Name is required', 'bit-pi'), $payload, 422);
        }

        if (empty($displayName)) {
            return WordPressActionHelper::response(__('Role Display Name is required', 'bit-pi'), $payload, 422);
        }

        if (get_role($roleName)) {
            return WordPressActionHelper::response(__('Role already exists.', 'bit-pi'), $payload, 422);
        }

        $newRole = add_role($roleName, $displayName, $capabilities);

        if (!$newRole) {
            return WordPressActionHelper::response(__('Failed to create the role.', 'bit-pi'), $payload, 500);
        }

        return WordPressActionHelper::response($newRole, $payload);
    }

    /**
     * Delete WordPress User Role.
     *
     * @return collection
     */
    public function deleteRole()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $name = $data['roleName'] ?? null;

        $payload = ['role_name' => $name];

        if (empty($name)) {
            return WordPressActionHelper::response(__('Role Name is required', 'bit-pi'), $payload, 422);
        }

        if (empty(get_role($name))) {
            return WordPressActionHelper::response(__('Role not found.', 'bit-pi'), $payload, 400);
        }

        remove_role($name);

        return WordPressActionHelper::response(__('Role deleted successfully.', 'bit-pi'), $payload);
    }

    /**
     * Get all WordPress Role.
     *
     * @return collection
     */
    public function getAllRoles()
    {
        return WordPressActionHelper::response(
            WordPressActionHelper::getWpRoles()
        );
    }

    /**
     * Add Or Remove or Change WordPress user Roles.
     *
     * @param bool $removeRole if true, roles will be removed; otherwise, they will be added
     * @param bool $updateRole
     *
     * @return collection
     */
    public function manageUserRole($removeRole = false, $updateRole = false)
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $userId = $data['userId'] ?? null;

        $userRole = Utility::convertStringToArray($data['userRole']);
        $customUserRoles = Utility::convertStringToArray($data['customUserRoles']);

        $isCustomUserRoleEnable = $this->nodeInfoProvider->getFieldMapConfigs('map-custom-role.value') ?? false;

        $payload = [
            'user_id'              => $userId,
            'user_role'            => $userRole,
            'custom_user_role'     => $customUserRoles,
            'map_custom_user_role' => $isCustomUserRoleEnable
        ];

        if (empty($userId)) {
            return WordPressActionHelper::response(__('User id is required', 'bit-pi'), $payload, 422);
        }

        if (!$isCustomUserRoleEnable && empty($userRole)) {
            return WordPressActionHelper::response(__('User role is required', 'bit-pi'), $payload, 422);
        }

        if ($isCustomUserRoleEnable && empty($customUserRoles)) {
            return WordPressActionHelper::response(__('Custom user role is required', 'bit-pi'), $payload, 422);
        }

        $user = new WP_User($userId);

        if (!$user->exists()) {
            return WordPressActionHelper::response(__('User not found.', 'bit-pi'), $payload, 400);
        }

        $finalRoles = $isCustomUserRoleEnable ? $customUserRoles : $userRole;

        if ($updateRole) {
            $user->set_role(array_shift($finalRoles));
        } else {
            foreach (array_unique($finalRoles) as $role) {
                if ($removeRole && \in_array($role, $user->roles)) {
                    $user->remove_role($role);
                } elseif (!$removeRole && !\in_array($role, $user->roles)) {
                    $user->add_role($role);
                }
            }
        }

        return WordPressActionHelper::response($user, $payload);
    }

    /**
     * Get all Capabilities.
     *
     * @return collection
     */
    public function getAllCapabilities()
    {
        $roles = WordPressActionHelper::getWpRoles();

        $capabilityKeys = [];

        foreach ($roles as $role) {
            if (!empty($role['capabilities'])) {
                $capabilityKeys[] = array_keys($role['capabilities']);
            }
        }

        $allCapabilities = array_unique(array_merge(...$capabilityKeys));

        sort($allCapabilities, SORT_STRING | SORT_FLAG_CASE);

        return WordPressActionHelper::response($allCapabilities);
    }

    /**
     * Get wordpress role capabilities.
     *
     * @return collection
     */
    public function getRoleCapabilities()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $userRole = Utility::convertStringToArray($data['userRole']);
        $customUserRoles = Utility::convertStringToArray($data['customUserRoles']);

        $mapCustomRole = $this->nodeInfoProvider->getFieldMapConfigs('map-custom-role.value') ?? false;

        $payload = [
            'user_role'            => $userRole,
            'custom_user_role'     => $customUserRoles,
            'map_custom_user_role' => $mapCustomRole
        ];

        if (!$mapCustomRole && empty($userRole)) {
            return WordPressActionHelper::response(__('User role is required', 'bit-pi'), $payload, 422);
        }

        if ($mapCustomRole && empty($customUserRoles)) {
            return WordPressActionHelper::response(__('Custom user role is required', 'bit-pi'), $payload, 422);
        }

        $finalRole = $mapCustomRole ? $customUserRoles : $userRole;
        $finalRole = \is_array($finalRole) ? reset($finalRole) : $finalRole;

        $roles = WordPressActionHelper::getWpRoles();

        if (empty($roles[$finalRole])) {
            return WordPressActionHelper::response(__('Role not found.', 'bit-pi'), $payload, 400);
        }

        $capabilities = array_keys($roles[$finalRole]['capabilities'] ?? []);

        sort($capabilities, SORT_STRING | SORT_FLAG_CASE);

        return WordPressActionHelper::response(array_unique($capabilities), $payload);
    }

    /**
     * Add Or Remove WordPress Role Capabilities.
     *
     * @param bool $removeCap if true, capabilities will be removed; otherwise, they will be added
     *
     * @return collection
     */
    public function manageRoleCapabilities($removeCap = false)
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $name = $data['roleName'] ?? null;
        $capabilities = Utility::convertStringToArray($data['roleCapabilities']);

        $payload = [
            'role_name'         => $name,
            'role_capabilities' => $capabilities
        ];

        if (empty($name)) {
            return WordPressActionHelper::response(__('Role Name is required', 'bit-pi'), $payload, 422);
        }

        if (empty($capabilities)) {
            return WordPressActionHelper::response(__('Role capabilities are required', 'bit-pi'), $payload, 422);
        }

        $role = get_role($name);

        if (empty($role)) {
            return WordPressActionHelper::response(__('Role not found.', 'bit-pi'), $payload, 400);
        }

        foreach (array_unique($capabilities) as $capability) {
            if ($removeCap && $role->has_cap($capability)) {
                $role->remove_cap($capability);
            } elseif (!$removeCap && !$role->has_cap($capability)) {
                $role->add_cap($capability);
            }
        }

        return WordPressActionHelper::response($role, $payload);
    }

    /**
     * Get wordpress User capabilities.
     *
     * @return collection
     */
    public function getUserCapabilities()
    {
        $userId = $this->nodeInfoProvider->getFieldMapData()['userId'] ?? null;

        $payload = ['user_id' => $userId];

        if (empty($userId)) {
            return WordPressActionHelper::response(__('User id is required', 'bit-pi'), $payload, 422);
        }

        $user = get_userdata($userId);

        if (empty($user)) {
            return WordPressActionHelper::response(__('User not found.', 'bit-pi'), $payload, 400);
        }

        $capabilities = [];

        foreach ($user->roles as $role) {
            $role_obj = get_role($role);

            if ($role_obj && !empty($role_obj->capabilities)) {
                $capabilities[] = array_keys($role_obj->capabilities ?? []);
            }
        }

        $allCapabilities = array_merge(...$capabilities);

        sort($allCapabilities, SORT_STRING | SORT_FLAG_CASE);

        return WordPressActionHelper::response(array_unique($allCapabilities), $payload);
    }

    /**
     * Add Or Remove WordPress User Capabilities.
     *
     * @param bool $removeCap if true, capabilities will be removed; otherwise, they will be added
     *
     * @return collection
     */
    public function manageUserCapabilities($removeCap = false)
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $userId = $data['userId'] ?? null;
        $capabilities = Utility::convertStringToArray($data['roleCapabilities']);

        $payload = [
            'user_id'           => $userId,
            'role_capabilities' => $capabilities
        ];

        if (empty($userId)) {
            return WordPressActionHelper::response(__('User id is required', 'bit-pi'), $payload, 422);
        }

        if (empty($capabilities)) {
            return WordPressActionHelper::response(__('Role capabilities are required', 'bit-pi'), $payload, 422);
        }

        $user = new WP_User($userId);

        if (!$user->exists()) {
            return WordPressActionHelper::response(__('User not found.', 'bit-pi'), $payload, 400);
        }

        foreach (array_unique($capabilities) as $capability) {
            if ($removeCap && $user->has_cap($capability)) {
                $user->remove_cap($capability);
            } elseif (!$removeCap && !$user->has_cap($capability)) {
                $user->add_cap($capability);
            }
        }

        return WordPressActionHelper::response($user, $payload);
    }

    /**
     * Get All Posts.
     *
     * @return collection
     */
    public function getAllPosts()
    {
        return WordPressActionHelper::response(
            WordPressActionHelper::getPosts()
        );
    }

    /**
     * Get Post.
     *
     * @return collection
     */
    public function getPostById()
    {
        $postId = $this->nodeInfoProvider->getFieldMapData()['postId'] ?? null;
        $payload = ['post_id' => $postId];


        if (empty($postId)) {
            return WordPressActionHelper::response(__('Post id is required', 'bit-pi'), $payload, 422);
        }

        return WordPressActionHelper::response(get_post($postId), $payload);
    }

    /**
     * Get Posts By Post Type.
     *
     * @return collection
     */
    public function getPostsByPostType()
    {
        $postType = $this->nodeInfoProvider->getFieldMapConfigs('post-type.value');

        $payload = ['post_type' => $postType];

        if (empty($postType)) {
            return WordPressActionHelper::response(__('Post Type is required', 'bit-pi'), $payload, 422);
        }

        return WordPressActionHelper::response(
            WordPressActionHelper::getPosts($postType),
            $payload
        );
    }

    /**
     * Get Posts By MetaData.
     *
     * @return collection
     */
    public function getPostsByMetadata()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();
        $postType = $this->nodeInfoProvider->getFieldMapConfigs('post-type.value');

        $metaKey = $data['metaKey'] ?? null;
        $metaValue = $data['metaValue'] ?? null;

        $payload = [
            'post_type'  => $postType,
            'meta_key'   => $metaKey, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Data payload, not a direct query
            'meta_value' => $metaValue, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Data payload, not a direct query
        ];

        if (empty($postType)) {
            return WordPressActionHelper::response(__('Post Type is required', 'bit-pi'), $payload, 422);
        }

        if (empty($metaKey)) {
            return WordPressActionHelper::response(__('Meta Key is required', 'bit-pi'), $payload, 422);
        }

        if (empty($metaValue)) {
            return WordPressActionHelper::response(__('Meta Value is required', 'bit-pi'), $payload, 422);
        }

        return WordPressActionHelper::response(
            WordPressActionHelper::getPosts(
                $postType,
                [
                    [
                        'key'   => $metaKey,
                        'value' => $metaValue
                    ],
                ]
            ),
            $payload
        );
    }

    /**
     * Get Post Metadata.
     *
     * @return collection
     */
    public function getPostMetadata()
    {
        $postId = $this->nodeInfoProvider->getFieldMapData()['postId'] ?? null;
        $payload = ['post_id' => $postId];

        if (empty($postId)) {
            return WordPressActionHelper::response(__('Post id is required', 'bit-pi'), $payload, 422);
        }

        return WordPressActionHelper::response(Utility::getPostMetadata($postId), $payload);
    }

    /**
     * Get WordPress Post Metadata by meta key.
     *
     * @return collection
     */
    public function getPostMetadataByMetaKey()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $postId = $data['postId'] ?? null;
        $metaKey = $data['metaKey'] ?? null;
        $payload = [
            'post_id'  => $postId,
            'meta_key' => $metaKey // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Data payload, not a direct query
        ];

        if (empty($postId)) {
            return WordPressActionHelper::response(__('Post id is required', 'bit-pi'), $payload, 422);
        }

        if (empty($metaKey)) {
            return WordPressActionHelper::response(__('Meta Key is required', 'bit-pi'), $payload, 422);
        }

        $metadata = Utility::getPostMetadata($postId, $metaKey, true);

        if (empty($metadata)) {
            return WordPressActionHelper::response(__('Post Metadata not found with provided id and meta key', 'bit-pi'), $payload, 400);
        }

        return WordPressActionHelper::response($metadata, $payload);
    }

    /**
     * Get Post Permalink.
     *
     * @return collection
     */
    public function getPostPermalink()
    {
        $postId = $this->nodeInfoProvider->getFieldMapData()['postId'] ?? null;
        $payload = ['post_id' => $postId];

        if (empty($postId)) {
            return WordPressActionHelper::response(__('Post id is required', 'bit-pi'), $payload, 422);
        }

        return WordPressActionHelper::response(get_permalink($postId), $payload);
    }

    /**
     * Get Post Content.
     *
     * @return collection
     */
    public function getPostContent()
    {
        $postId = $this->nodeInfoProvider->getFieldMapData()['postId'] ?? null;
        $payload = ['post_id' => $postId];

        if (empty($postId)) {
            return WordPressActionHelper::response(__('Post id is required', 'bit-pi'), $payload, 422);
        }

        return WordPressActionHelper::response(get_the_content($postId), $payload);
    }

    /**
     * Get Post Excerpt.
     *
     * @return collection
     */
    public function getPostExcerpt()
    {
        $postId = $this->nodeInfoProvider->getFieldMapData()['postId'] ?? null;
        $payload = ['post_id' => $postId];

        if (empty($postId)) {
            return WordPressActionHelper::response(__('Post id is required', 'bit-pi'), $payload, 422);
        }

        return WordPressActionHelper::response(get_the_excerpt($postId), $payload);
    }

    /**
     * Get Post Status.
     *
     * @return collection
     */
    public function getPostStatus()
    {
        $postId = $this->nodeInfoProvider->getFieldMapData()['postId'] ?? null;
        $payload = ['post_id' => $postId];

        if (empty($postId)) {
            return WordPressActionHelper::response(__('Post id is required', 'bit-pi'), $payload, 422);
        }

        return WordPressActionHelper::response(get_post_status($postId), $payload);
    }

    /**
     * Create WordPress Post.
     *
     * @return collection
     */
    public function createNewPost()
    {
        $fields = $this->nodeInfoProvider->getFieldMapData();

        $fields['postType'] = $this->nodeInfoProvider->getFieldMapConfigs('post-type.value') ?? null;
        $fields['postStatus'] = $this->nodeInfoProvider->getFieldMapConfigs('post-status.value') ?? null;
        $fields['postAuthor'] = $this->nodeInfoProvider->getFieldMapConfigs('user.value') ?? null;
        $fields['taxonomy'] = $this->nodeInfoProvider->getFieldMapConfigs('taxonomy.value') ?? null;
        $fields['terms'] = $this->nodeInfoProvider->getFieldMapConfigs('terms.value') ?? null;
        $fields['categories'] = $this->nodeInfoProvider->getFieldMapConfigs('post-category.value') ?? null;
        $fields['customField'] = $this->nodeInfoProvider->getFieldMapRepeaters('custom-field.value', false, true, 'key', 'value');
        $isEnabledFeaturedImageId = $this->nodeInfoProvider->getFieldMapConfigs('switch-featured-image-id.value') ?? false;

        $payload = WordPressActionHelper::mapPostPayload($fields);

        if (empty($fields['title'])) {
            return WordPressActionHelper::response(__('Post title is required', 'bit-pi'), $payload, 422);
        }

        if (empty($fields['postType'])) {
            return WordPressActionHelper::response(__('Post Type is required', 'bit-pi'), $payload, 422);
        }

        if (empty($fields['postStatus'])) {
            return WordPressActionHelper::response(__('Post Status is required', 'bit-pi'), $payload, 422);
        }

        $postId = wp_insert_post(
            WordPressActionHelper::mapPostFields($fields),
            true
        );

        if (is_wp_error($postId)) {
            return WordPressActionHelper::response(__('Failed to create post.', 'bit-pi'), $payload, 400);
        }

        if (!empty($fields['terms'])) {
            wp_set_object_terms($postId, $fields['terms'], $fields['taxonomy']);
        }

        if (!empty($fields['tags'])) {
            wp_add_post_tags($postId, $fields['tags']);
        }

        $error = WordPressActionHelper::setPostFeaturedImage($postId, $fields, $isEnabledFeaturedImageId);
        if ($error) {
            return $error;
        }

        return WordPressActionHelper::response(['post_id' => $postId], $payload);
    }

    /**
     * Update WordPress Post.
     *
     * @return collection
     */
    public function updateExistingPost()
    {
        $fields = $this->nodeInfoProvider->getFieldMapData();

        $fields['terms'] = $this->nodeInfoProvider->getFieldMapConfigs('terms.value') ?? null;
        $fields['taxonomy'] = $this->nodeInfoProvider->getFieldMapConfigs('taxonomy.value') ?? null;
        $fields['postType'] = $this->nodeInfoProvider->getFieldMapConfigs('post-type.value') ?? null;
        $fields['postStatus'] = $this->nodeInfoProvider->getFieldMapConfigs('post-status.value') ?? null;
        $fields['postAuthor'] = $this->nodeInfoProvider->getFieldMapConfigs('user.value') ?? null;
        $fields['categories'] = $this->nodeInfoProvider->getFieldMapConfigs('post-category.value') ?? null;
        $fields['customField'] = $this->nodeInfoProvider->getFieldMapRepeaters('custom-field.value', false, true, 'key', 'value');
        $isEnabledFeaturedImageId = $this->nodeInfoProvider->getFieldMapConfigs('switch-featured-image-id.value') ?? false;

        $payload = WordPressActionHelper::mapPostPayload($fields);

        if (empty($fields['postId'])) {
            return WordPressActionHelper::response(__('Post id is required', 'bit-pi'), $payload, 422);
        }

        $postId = wp_update_post(
            WordPressActionHelper::mapPostFields($fields),
            true
        );

        if (is_wp_error($postId)) {
            return WordPressActionHelper::response(__('Failed to update post.', 'bit-pi'), $payload, 400);
        }

        if (!empty($fields['terms'])) {
            wp_set_object_terms($postId, $fields['terms'], $fields['taxonomy'], true);
        }

        if (!empty($fields['tags'])) {
            wp_set_post_tags($postId, $fields['tags'], true);
        }

        $error = WordPressActionHelper::setPostFeaturedImage($postId, $fields, $isEnabledFeaturedImageId);
        if ($error) {
            return $error;
        }

        return WordPressActionHelper::response(['post_id' => $postId], $payload);
    }

    /**
     * Update WordPress Post Status.
     *
     * @return collection
     */
    public function updatePostStatus()
    {
        $postId = $this->nodeInfoProvider->getFieldMapData()['postId'] ?? null;

        $postStatus = $this->nodeInfoProvider->getFieldMapConfigs('post-status.value') ?? null;

        $payload = ['post_id' => $postId, 'status' => $postStatus];

        if (empty($postId)) {
            return WordPressActionHelper::response(__('Post id is required', 'bit-pi'), $payload, 422);
        }

        $postId = wp_update_post(
            [
                'ID'          => $postId,
                'post_status' => $postStatus,
            ],
            true
        );

        if (is_wp_error($postId)) {
            return WordPressActionHelper::response(__('Failed to update post status.', 'bit-pi'), $payload, 400);
        }

        return WordPressActionHelper::response($payload, $payload);
    }

    /**
     * Delete Post.
     *
     * @return collection
     */
    public function deleteExistingPost()
    {
        $postId = $this->nodeInfoProvider->getFieldMapData()['id'] ?? null;
        $forceDelete = $this->nodeInfoProvider->getFieldMapConfigs('force-delete.value');

        $payload = ['post_id' => $postId, 'force_delete' => $forceDelete];

        if (empty($postId)) {
            return WordPressActionHelper::response(__('Post id is required', 'bit-pi'), $payload, 422);
        }

        $result = wp_delete_post($postId, $forceDelete);

        if (empty($result)) {
            return WordPressActionHelper::response(__('Failed to deleted post', 'bit-pi'), $payload, 500);
        }

        return WordPressActionHelper::response(__('Post deleted successfully', 'bit-pi'), $payload);
    }

    /**
     * Get All Post Comments.
     *
     * @return collection
     */
    public function getAllPostComments()
    {
        return WordPressActionHelper::response(
            WordPressActionHelper::getComments()
        );
    }

    /**
     * Get Post Comments.
     *
     * @return collection
     */
    public function getPostComments()
    {
        $postId = $this->nodeInfoProvider->getFieldMapData()['postId'] ?? null;
        $payload = ['post_id' => $postId];

        if (empty($postId)) {
            return WordPressActionHelper::response(__('Post id is required', 'bit-pi'), $payload, 422);
        }

        return WordPressActionHelper::response(
            WordPressActionHelper::getComments($postId),
            $payload
        );
    }

    /**
     * Get User Comments.
     *
     * @return collection
     */
    public function getUserComments()
    {
        $userId = $this->nodeInfoProvider->getFieldMapData()['userId'] ?? null;
        $payload = ['user_id' => $userId];

        if (empty($userId)) {
            return WordPressActionHelper::response(__('User id is required', 'bit-pi'), $payload, 422);
        }

        return WordPressActionHelper::response(
            WordPressActionHelper::getComments(null, $userId),
            $payload
        );
    }

    /**
     * Get User Comments by Email.
     *
     * @return collection
     */
    public function getUserCommentsByEmail()
    {
        $userEmail = $this->nodeInfoProvider->getFieldMapData()['userEmail'] ?? null;
        $payload = ['user_email' => $userEmail];

        if (empty($userEmail)) {
            return WordPressActionHelper::response(__('User Email Address is required', 'bit-pi'), $payload, 422);
        }

        return WordPressActionHelper::response(
            WordPressActionHelper::getComments(null, null, $userEmail),
            $payload
        );
    }

    /**
     * Get Comment Metadata.
     *
     * @return collection
     */
    public function getCommentMetadata()
    {
        $commentId = $this->nodeInfoProvider->getFieldMapData()['commentId'] ?? null;

        $payload = ['comment_id' => $commentId];

        if (empty($commentId)) {
            return WordPressActionHelper::response(__('Comment id is required', 'bit-pi'), $payload, 422);
        }

        return WordPressActionHelper::response(
            Utility::getCommentMetadata($commentId),
            $payload
        );
    }

    /**
     * Get Comment Metadata by Meta Key.
     *
     * @return collection
     */
    public function getCommentMetadataByMetaKey()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $commentId = $data['commentId'] ?? null;
        $metaKey = $data['metaKey'] ?? null;
        $payload = [
            'comment_id' => $commentId,
            'meta_key'   => $metaKey // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Data payload, not a direct query
        ];

        if (empty($commentId)) {
            return WordPressActionHelper::response(__('Comment id is required', 'bit-pi'), $payload, 422);
        }

        if (empty($metaKey)) {
            return WordPressActionHelper::response(__('Meta Key is required', 'bit-pi'), $payload, 422);
        }

        $metadata = Utility::getCommentMetadata($commentId, $metaKey, true);

        if (empty($metadata)) {
            return WordPressActionHelper::response(__('Comment Metadata not found with provided id and meta key', 'bit-pi'), $payload, 400);
        }

        return WordPressActionHelper::response($metadata, $payload);
    }

    /**
     * Create New Comment.
     *
     * @return collection
     */
    public function createNewComment()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $postId = $data['postId'] ?? null;
        $comment = $data['comment'] ?? null;
        $authorName = $data['authorName'] ?? null;
        $authorEmail = $data['authorEmail'] ?? null;
        $authorURL = $data['authorURL'] ?? null;

        $payload = [
            'post_id'      => $postId,
            'comment'      => $comment,
            'author_name'  => $authorName,
            'author_email' => $authorEmail,
            'author_url'   => $authorURL,
        ];

        if (empty($postId)) {
            return WordPressActionHelper::response(__('Post id is required', 'bit-pi'), $payload, 422);
        }

        if (empty($comment)) {
            return WordPressActionHelper::response(__('Comment is required', 'bit-pi'), $payload, 422);
        }

        if (empty($authorName)) {
            return WordPressActionHelper::response(__('Author name is required', 'bit-pi'), $payload, 422);
        }

        $commentId = wp_new_comment(WordPressActionHelper::mapCommentFields($data), true);

        if (is_wp_error($commentId)) {
            return WordPressActionHelper::response($commentId, $payload, (int) $commentId->get_error_code());
        }

        $comment = get_comment($commentId);

        return WordPressActionHelper::response($comment, $payload);
    }

    /**
     * Reply to Comment.
     *
     * @return collection
     */
    public function replyToComment()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $postId = $data['postId'] ?? null;
        $parentId = $data['parentId'] ?? null;
        $comment = $data['comment'] ?? null;
        $authorName = $data['authorName'] ?? null;
        $authorEmail = $data['authorEmail'] ?? null;
        $authorURL = $data['authorURL'] ?? null;

        $payload = [
            'post_id'      => $postId,
            'comment_id'   => $parentId,
            'comment'      => $comment,
            'author_name'  => $authorName,
            'author_email' => $authorEmail,
            'author_url'   => $authorURL,
        ];

        if (empty($postId)) {
            return WordPressActionHelper::response(__('Post id is required', 'bit-pi'), $payload, 422);
        }

        if (empty($parentId)) {
            return WordPressActionHelper::response(__('comment id is required', 'bit-pi'), $payload, 422);
        }

        if (empty($comment)) {
            return WordPressActionHelper::response(__('Comment is required', 'bit-pi'), $payload, 422);
        }

        if (empty($authorName)) {
            return WordPressActionHelper::response(__('Author name is required', 'bit-pi'), $payload, 422);
        }

        $comment = wp_new_comment(WordPressActionHelper::mapCommentFields($data));

        if (is_wp_error($comment)) {
            return WordPressActionHelper::response(__('Failed to reply comment.', 'bit-pi'), $payload, $comment->get_error_code());
        }

        return WordPressActionHelper::response($comment, $payload);
    }

    /**
     * Delete Comments.
     *
     * @return collection
     */
    public function deleteExistingComment()
    {
        $commentId = $this->nodeInfoProvider->getFieldMapData()['id'] ?? null;
        $forceDelete = $this->nodeInfoProvider->getFieldMapConfigs('force-delete.value');

        $payload = ['comment_id' => $commentId, 'force_delete' => $forceDelete];

        if (empty($commentId)) {
            return WordPressActionHelper::response(__('Comment id is required', 'bit-pi'), $payload, 422);
        }

        $status = wp_delete_comment($commentId, $forceDelete);

        if (!$status) {
            return WordPressActionHelper::response(__('Failed deleted comment', 'bit-pi'), $payload, 500);
        }

        return WordPressActionHelper::response(
            __('Comment deleted successfully', 'bit-pi'),
            $payload
        );
    }

    /**
     * Get All Post Types.
     *
     * @return collection
     */
    public function getAllPostTypes()
    {
        return WordPressActionHelper::response(get_post_types([], 'objects'));
    }

    /**
     * Get Post Type.
     *
     * @return collection
     */
    public function getPostType()
    {
        $postId = $this->nodeInfoProvider->getFieldMapData()['postId'] ?? null;
        $payload = ['post_id' => $postId];


        if (empty($postId)) {
            return WordPressActionHelper::response(__('Post id is required', 'bit-pi'), $payload, 422);
        }

        return WordPressActionHelper::response(get_post_type($postId));
    }

    /**
     * Register WordPress Post Type.
     *
     * @return collection
     */
    public function registerPostType()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $key = $data['key'] ?? null;
        $label = $data['label'] ?? null;
        $hierarchy = $data['hierarchy'] ?? null;
        $public = $data['public'] ?? null;
        $showUI = $data['showUI'] ?? null;
        $showInMenu = $data['showInMenu'] ?? null;
        $showInNavMenu = $data['showInNavMenu'] ?? null;
        $showInAdminBar = $data['showInAdminBar'] ?? null;
        $menuPosition = $data['menuPosition'] ?? null;
        $description = $data['description'] ?? null;
        $rewriteSlug = $data['rewriteSlug'] ?? null;
        $supports = Utility::convertStringToArray($data['supports']);


        $payload = [
            'post_type_key'     => $key,
            'post_type_label'   => $label,
            'hierarchy'         => $hierarchy,
            'public'            => $public,
            'show_ui'           => $showUI,
            'show_in_menu'      => $showInMenu,
            'show_in_nav_menu'  => $showInNavMenu,
            'show_in_admin_bar' => $showInAdminBar,
            'menu_position'     => $menuPosition,
            'supports'          => $supports,
            'description'       => $description,
            'custom_url_slug'   => $rewriteSlug,
        ];

        if (empty($key)) {
            return WordPressActionHelper::response(__('Post Type Key is required', 'bit-pi'), $payload, 422);
        }

        $postType = register_post_type(
            $key,
            [
                'label'             => $label,
                'public'            => $public,
                'hierarchical'      => $hierarchy,
                'show_ui'           => $showUI,
                'show_in_menu'      => $showInMenu,
                'show_in_nav_menus' => $showInNavMenu,
                'show_in_admin_bar' => $showInAdminBar,
                'menu_position'     => $menuPosition,
                'supports'          => $supports,
                'description'       => $description,
                'rewrite'           => ['slug' => $rewriteSlug],
            ]
        );

        if (is_wp_error($postType)) {
            return WordPressActionHelper::response(__('Failed to register post type.', 'bit-pi'), $payload, 400);
        }

        return WordPressActionHelper::response($postType, $payload);
    }

    /**
     * Unregister WordPress Post Type.
     *
     * @return collection
     */
    public function unregisterPostType()
    {
        $key = $this->nodeInfoProvider->getFieldMapData()['key'] ?? null;

        $payload = ['post_type_key' => $key];

        if (empty($key)) {
            return WordPressActionHelper::response(__('Post Type Key is required', 'bit-pi'), $payload, 422);
        }

        if (!post_type_exists($key)) {
            return WordPressActionHelper::response(__('Post Type not found.', 'bit-pi'), $payload, 400);
        }

        $status = unregister_post_type($key);

        if (is_wp_error($status)) {
            return WordPressActionHelper::response(__('Failed to unregister post type.', 'bit-pi'), $payload, 400);
        }

        return WordPressActionHelper::response(__('Successfully unregistered the Post Type.', 'bit-pi'), $payload);
    }

    /**
     * Add WordPress Post Type Features.
     *
     * @return collection
     */
    public function addPostTypeFeatures()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $key = $data['key'] ?? null;
        $supports = $data['supports'] ?? null;

        $payload = ['post_type_key' => $key, 'supports' => $supports];

        if (empty($key)) {
            return WordPressActionHelper::response(__('Post Type Key is required', 'bit-pi'), $payload, 422);
        }

        if (empty($supports)) {
            return WordPressActionHelper::response(__('Features (supports) are required', 'bit-pi'), $payload, 422);
        }

        if (!post_type_exists($key)) {
            return WordPressActionHelper::response(__('Post Type not found.', 'bit-pi'), $payload, 400);
        }

        add_post_type_support($key, $supports);

        return WordPressActionHelper::response(null, $payload);
    }

    /**
     * Add Taxonomy To Post.
     *
     * @return collection
     */
    public function addTaxonomyToPost()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $postId = $data['postId'] ?? null;
        $taxonomy = $data['taxonomy'] ?? null;
        $append = $data['append'] ?? false;
        $terms = Utility::convertStringToArray($data['term']);

        $payload = [
            'post_id'  => $postId,
            'taxonomy' => $taxonomy,
            'terms'    => $terms,
            'append'   => $append
        ];

        if (empty($postId)) {
            return WordPressActionHelper::response(__('Post id is required', 'bit-pi'), $payload, 422);
        }

        if (empty($taxonomy)) {
            return WordPressActionHelper::response(__('Taxonomy is required', 'bit-pi'), $payload, 422);
        }

        if (empty($terms)) {
            return WordPressActionHelper::response(__('Term is required', 'bit-pi'), $payload, 422);
        }

        $result = wp_set_object_terms($postId, $terms, $taxonomy, $append);

        if (is_wp_error($result)) {
            return WordPressActionHelper::response(__('Failed add taxonomy to post.', 'bit-pi'), $payload, 400);
        }

        return WordPressActionHelper::response($result, $payload);
    }

    /**
     * Removed Taxonomy from Post.
     *
     * @return collection
     */
    public function removeTaxonomyFromPost()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $postId = $data['postId'] ?? null;
        $taxonomy = $data['taxonomy'] ?? null;
        $terms = Utility::convertStringToArray($data['term']);

        $payload = [
            'post_id'  => $postId,
            'taxonomy' => $taxonomy,
            'terms'    => $terms,
        ];

        if (empty($postId)) {
            return WordPressActionHelper::response(__('Post id is required', 'bit-pi'), $payload, 422);
        }

        if (empty($taxonomy)) {
            return WordPressActionHelper::response(__('Taxonomy is required', 'bit-pi'), $payload, 422);
        }

        if (empty($terms)) {
            return WordPressActionHelper::response(__('Term is required', 'bit-pi'), $payload, 422);
        }

        $status = wp_remove_object_terms($postId, $terms, $taxonomy);

        if (!$status || is_wp_error($status)) {
            return WordPressActionHelper::response(__('Taxonomy does not removed', 'bit-pi'), $payload, 500);
        }

        return WordPressActionHelper::response(__('Taxonomy removed Successfully', 'bit-pi'), $payload);
    }

    /**
     * Set Tags for Post.
     *
     * @return collection
     */
    public function addTagsToPost()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $postId = $data['postId'] ?? null;
        $tags = $data['tags'] ?? null;
        $append = $data['append'] ?? false;

        $payload = [
            'post_id' => $postId,
            'tag_ids' => $tags,
            'append'  => $append,
        ];

        if (empty($postId)) {
            return WordPressActionHelper::response(__('Post id is required', 'bit-pi'), $payload, 422);
        }

        if (empty($tags)) {
            return WordPressActionHelper::response(__('Post Tags are required', 'bit-pi'), $payload, 422);
        }

        $result = wp_set_post_terms($postId, $tags, 'post_tag', $append);

        if (is_wp_error($result)) {
            return WordPressActionHelper::response(__('Failed to add tags to post.', 'bit-pi'), $payload, 400);
        }

        return WordPressActionHelper::response($result, $payload);
    }

    /**
     * Set Tags for Post.
     *
     * @return collection
     */
    public function removeTagsFromPost()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $postId = $data['postId'] ?? null;
        $tags = $data['tags'] ?? null;

        $payload = [
            'post_id' => $postId,
            'tag_ids' => $tags,
        ];

        if (empty($postId)) {
            return WordPressActionHelper::response(__('Post id is required', 'bit-pi'), $payload, 422);
        }

        if (empty($tags)) {
            return WordPressActionHelper::response(__('Post Tags are required', 'bit-pi'), $payload, 422);
        }

        $status = wp_remove_object_terms($postId, $tags, 'post_tag');

        if (!$status) {
            return WordPressActionHelper::response(__('Tags does not removed', 'bit-pi'), $payload, 500);
        }

        return WordPressActionHelper::response(__('Tags removed Successfully', 'bit-pi'), $payload);
    }

    /**
     * Add new image to media library.
     *
     * @return collection
     */
    public function addNewImage()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $url = filter_var($data['url'] ?? '', FILTER_SANITIZE_URL);
        $title = $data['title'] ?? null;
        $altText = $data['altText'] ?? null;
        $caption = $data['caption'] ?? null;
        $description = $data['description'] ?? null;

        $payload = [
            'Image_url'        => $url,
            'title'            => $title,
            'alternative_text' => $altText,
            'caption'          => $caption,
            'description'      => $description,
        ];

        if (empty($url)) {
            return WordPressActionHelper::response(__('Image URL is required', 'bit-pi'), $payload, 422);
        }

        include_once ABSPATH . 'wp-admin/includes/media.php';

        include_once ABSPATH . 'wp-admin/includes/file.php';

        include_once ABSPATH . 'wp-admin/includes/image.php';


        $imageId = media_sideload_image($url, 0, null, 'id');

        if (is_wp_error($imageId)) {
            return WordPressActionHelper::response(__('Failed to upload image.', 'bit-pi'), $payload, $imageId->get_error_code());
        }

        $attachmentUrl = wp_get_attachment_url((int) $imageId);

        if (empty($attachmentUrl)) {
            return WordPressActionHelper::response(__('Failed to uploaded image.', 'bit-pi'), $payload, 500);
        }

        $mimeType = wp_check_filetype(basename($attachmentUrl))['type'] ?? '';

        $imageData = [
            'ID'             => (int) $imageId,
            'post_title'     => $title,
            'post_excerpt'   => $caption,
            'post_content'   => $description,
            'post_mime_type' => $mimeType,
            'guid'           => $attachmentUrl,
        ];

        $updated = wp_insert_attachment($imageData);

        if (is_wp_error($updated) || $updated == 0) {
            return WordPressActionHelper::response(__('Failed to update image metadata.', 'bit-pi'), $payload, 500);
        }

        if (!empty($altText)) {
            update_post_meta($imageId, '_wp_attachment_image_alt', $altText);
        }

        return WordPressActionHelper::response(
            [
                'image_id'       => $imageId,
                'image_url'      => $attachmentUrl,
                'image_details'  => $imageData,
                'image_alt_text' => $altText,
            ],
            $payload
        );
    }

    /**
     * Delete media from media library.
     *
     * @return collection
     */
    public function deleteMedia()
    {
        $id = $this->nodeInfoProvider->getFieldMapData()['id'] ?? null;
        $forceDelete = $this->nodeInfoProvider->getFieldMapConfigs('force-delete.value');

        $payload = ['media_id' => $id, 'force_delete' => $forceDelete];

        if (empty($id)) {
            return WordPressActionHelper::response(__('Media id is required', 'bit-pi'), $payload, 422);
        }

        $status = wp_delete_attachment($id, $forceDelete);

        if (!$status) {
            return WordPressActionHelper::response(__('Failed deleted media', 'bit-pi'), $payload, 500);
        }

        return WordPressActionHelper::response(
            __('Media deleted successfully', 'bit-pi'),
            $payload
        );
    }

    /**
     * Rename media.
     *
     * @return collection
     */
    public function renameMedia()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $id = $data['id'] ?? null;
        $title = $data['title'] ?? null;

        $payload = ['media_id' => $id, 'new_title' => $title];

        if (empty($id)) {
            return WordPressActionHelper::response(__('Media id is required', 'bit-pi'), $payload, 422);
        }

        if (empty($title)) {
            return WordPressActionHelper::response(__('Media new title is required', 'bit-pi'), $payload, 422);
        }

        $media = get_post($id);

        if (empty($media) || $media->post_type !== 'attachment') {
            return WordPressActionHelper::response(__('Media not found with the provided ID or invalid media type.', 'bit-pi'), $payload, 400);
        }

        $status = wp_update_post(['ID' => $id, 'post_title' => $title]);

        if (is_wp_error($status) || !$status) {
            return WordPressActionHelper::response(__('Failed to rename media.', 'bit-pi'), $payload, 500);
        }

        return WordPressActionHelper::response(
            __('Media renamed successfully', 'bit-pi'),
            $payload
        );
    }

    /**
     * Get all media.
     *
     * @return collection
     */
    public function getAllMedia()
    {
        return WordPressActionHelper::response(
            WordPressActionHelper::getPosts('attachment', null, null, 'inherit')
        );
    }

    /**
     * Get media by title.
     *
     * @return collection
     */
    public function getMediaByTitle()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $title = $data['title'] ?? null;

        $payload = ['media_title' => $title];

        if (empty($title)) {
            return WordPressActionHelper::response(__('Media title is required', 'bit-pi'), $payload, 422);
        }

        $media = WordPressActionHelper::getPosts('attachment', null, $title, 'inherit');

        if (empty($media)) {
            return WordPressActionHelper::response(__('Media not found with the provided title.', 'bit-pi'), $payload, 400);
        }

        return WordPressActionHelper::response(reset($media), $payload);
    }

    /**
     * Get media by id.
     *
     * @return collection
     */
    public function getMediaById()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $id = $data['id'] ?? null;

        $payload = ['media_id' => $id];

        if (empty($id)) {
            return WordPressActionHelper::response(__('Media id is required', 'bit-pi'), $payload, 422);
        }

        $media = get_post($id);

        if (empty($media)) {
            return WordPressActionHelper::response(__('Media not found with the provided id.', 'bit-pi'), $payload, 400);
        }

        return WordPressActionHelper::response($media, $payload);
    }

    /**
     * Get All WordPress Taxonomies.
     *
     * @return collection
     */
    public function getAllTaxonomies()
    {
        return WordPressActionHelper::response(get_taxonomies([], 'objects'));
    }

    /**
     * Get a WordPress Taxonomy.
     *
     * @return collection
     */
    public function getTaxonomy()
    {
        $slug = $this->nodeInfoProvider->getFieldMapData()['slug'] ?? null;

        $payload = ['taxonomy_slug' => $slug];

        if (empty($slug)) {
            return WordPressActionHelper::response(__('Taxonomy Identifier is required', 'bit-pi'), $payload, 422);
        }

        $taxonomy = get_taxonomy($slug);

        if (!$taxonomy) {
            return WordPressActionHelper::response(__('Taxonomy not found with provided slug', 'bit-pi'), $payload, 400);
        }

        return WordPressActionHelper::response($taxonomy, $payload);
    }

    /**
     * Get All WordPress Terms.
     *
     * @param null|string $taxonomy
     *
     * @return collection
     */
    public function getAllTerms($taxonomy = null)
    {
        return WordPressActionHelper::getTermsExecute($taxonomy);
    }

    /**
     * Get a WordPress Term.
     *
     * @return collection
     */
    public function getTerm()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $termId = $data['termId'] ?? null;
        $taxonomy = $data['slug'] ?? null;
        $payload = [
            'term_id'  => $termId,
            'taxonomy' => $taxonomy,
        ];

        return WordPressActionHelper::getTermExecute($termId, $taxonomy, $payload);
    }

    /**
     * Get WordPress term by Field.
     *
     * @return collection
     */
    public function getTermByField()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $fieldKey = $data['fieldKey'] ?? null;
        $fieldValue = $data['fieldValue'] ?? null;
        $taxonomy = $data['taxonomy'] ?? null;
        $payload = [
            'field_key'   => $fieldKey,
            'field_value' => $fieldValue,
            'taxonomy'    => $taxonomy
        ];

        if (empty($fieldKey)) {
            return WordPressActionHelper::response(__('Field is required', 'bit-pi'), $payload, 422);
        }

        if (empty($fieldValue)) {
            return WordPressActionHelper::response(__('Field Value is required', 'bit-pi'), $payload, 422);
        }

        if ($fieldKey === 'term_taxonomy_id' && empty($taxonomy)) {
            return WordPressActionHelper::response(__("Taxonomy is required, if field is 'term_taxonomy_id'", 'bit-pi'), $payload, 422);
        }

        $term = get_term_by($fieldKey, $fieldValue, $taxonomy);

        if (!$term) {
            return WordPressActionHelper::response(__('Term not found with provided field & value or taxonomy', 'bit-pi'), $payload, 400);
        }

        return WordPressActionHelper::response($term, $payload);
    }

    /**
     * Get WordPress term by Taxonomy.
     *
     * @return collection
     */
    public function getTermByTaxonomy()
    {
        $taxonomy = $this->nodeInfoProvider->getFieldMapData()['taxonomy'] ?? null;

        $payload = ['taxonomy' => $taxonomy];

        if (empty($taxonomy)) {
            return WordPressActionHelper::response(__("Taxonomy is required'", 'bit-pi'), $payload, 422);
        }

        return WordPressActionHelper::getTermsExecute($taxonomy, $payload);
    }

    /**
     * Create a WordPress Term.
     *
     * @return collection
     */
    public function createNewTerm()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $name = $data['name'] ?? null;
        $taxonomy = $data['taxonomy'] ?? null;
        $description = $data['description'] ?? null;
        $slug = $data['slug'] ?? null;

        $payload = [
            'term_name'        => $name,
            'term_taxonomy'    => $taxonomy,
            'term_description' => $description,
            'term_slug'        => $slug,
        ];

        return WordPressActionHelper::insertTermExecute(
            $name,
            $taxonomy,
            $payload,
            $slug,
            $description
        );
    }

    /**
     * Update a WordPress Term.
     *
     * @return collection
     */
    public function updateTerm()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $termId = $data['termId'] ?? null;
        $name = $data['name'] ?? null;
        $taxonomy = $data['taxonomy'] ?? null;
        $slug = $data['slug'] ?? null;
        $description = $data['description'] ?? null;

        $payload = [
            'term_id'          => $termId,
            'term_name'        => $name,
            'term_taxonomy'    => $taxonomy,
            'term_description' => $description,
            'term_slug'        => $slug,
        ];

        return WordPressActionHelper::updateTermExecute(
            $termId,
            $taxonomy,
            $payload,
            $name,
            $slug,
            $description
        );
    }

    /**
     * Delete  WordPress Term.
     *
     * @return collection
     */
    public function deleteTerm()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $termId = $data['termId'] ?? null;
        $taxonomy = $data['slug'] ?? null;
        $payload = [
            'term_id'       => $termId,
            'taxonomy_slug' => $taxonomy,
        ];

        return WordPressActionHelper::deleteTermExecute(
            $termId,
            $taxonomy,
            $payload,
            __('Term deleted successfully', 'bit-pi'),
            __('Failed to delete term', 'bit-pi')
        );
    }

    /**
     * Register WordPress Taxonomy.
     *
     * @return collection
     */
    public function registerTaxonomy()
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $slug = $data['slug'] ?? null;
        $name = $data['name'] ?? null;
        $postTypes = $data['postTypes'] ?? null;
        $hierarchy = $data['hierarchy'] ?? null;
        $public = $data['public'] ?? null;
        $adminUI = $data['adminUI'] ?? null;
        $restApi = $data['restApi'] ?? null;
        $description = $data['description'] ?? null;
        $rewriteSlug = $data['rewriteSlug'] ?? null;


        $payload = [
            'taxonomy_slug'     => $slug,
            'taxonomy_name'     => $name,
            'assign_post_types' => $postTypes,
            'hierarchy'         => $hierarchy,
            'public'            => $public,
            'show_admin_ui'     => $adminUI,
            'rest_api'          => $restApi,
            'description'       => $description,
            'custom_url_slug'   => $rewriteSlug,
        ];

        if (empty($slug)) {
            return WordPressActionHelper::response(__('Taxonomy Identifier is required', 'bit-pi'), $payload, 422);
        }

        if (empty($name)) {
            return WordPressActionHelper::response(__('Taxonomy Name is required', 'bit-pi'), $payload, 422);
        }

        if (empty($postTypes)) {
            return WordPressActionHelper::response(__('Post Types is required', 'bit-pi'), $payload, 422);
        }

        $taxonomy = register_taxonomy(
            $slug,
            $postTypes,
            [
                'label'        => $name,
                'public'       => $public,
                'show_ui'      => $adminUI,
                'hierarchical' => $hierarchy,
                'description'  => $description,
                'rewrite'      => ['slug' => $rewriteSlug],
                'show_in_rest' => $restApi
            ]
        );

        if (is_wp_error($taxonomy)) {
            return WordPressActionHelper::response(__('Failed to register taxonomy.', 'bit-pi'), $payload, 400);
        }

        return WordPressActionHelper::response($taxonomy, $payload);
    }

    /**
     * Unregister WordPress Taxonomy.
     *
     * @return collection
     */
    public function unregisterTaxonomy()
    {
        $slug = $this->nodeInfoProvider->getFieldMapData()['slug'] ?? null;

        $payload = ['taxonomy_slug' => $slug];

        if (empty($slug)) {
            return WordPressActionHelper::response(__('Taxonomy Identifier is required', 'bit-pi'), $payload, 422);
        }

        if (!taxonomy_exists($slug)) {
            return WordPressActionHelper::response(__('Taxonomy not found with provided slug', 'bit-pi'), $payload, 400);
        }

        $status = unregister_taxonomy($slug);

        if (!$status) {
            return WordPressActionHelper::response(__('Failed to unregister the taxonomy.', 'bit-pi'), $payload, 400);
        }

        return WordPressActionHelper::response(__('Successfully unregistered the taxonomy.', 'bit-pi'), $payload);
    }

    /**
     * Get a term by id.
     *
     * @param string $taxonomy
     *
     * @return collection
     */
    public function getTermById($taxonomy)
    {
        $id = $this->nodeInfoProvider->getFieldMapData()['id'] ?? null;

        return WordPressActionHelper::getTermExecute(
            $id,
            $taxonomy,
            [
                'id' => $id
            ]
        );
    }

    /**
     * Create a Term by taxonomy.
     *
     * @param string $taxonomy
     *
     * @return collection
     */
    public function createTermByTax($taxonomy)
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $name = $data['name'] ?? null;
        $slug = $data['slug'] ?? null;
        $description = $data['description'] ?? null;

        $payload = ['name' => $name, 'slug' => $slug, 'description' => $description];

        return WordPressActionHelper::insertTermExecute($name, $taxonomy, $payload, $slug, $description);
    }

    /**
     * Update a Product Category.
     *
     * @param string $taxonomy
     *
     * @return collection
     */
    public function updateTermByTax($taxonomy)
    {
        $data = $this->nodeInfoProvider->getFieldMapData();

        $id = $data['id'] ?? null;
        $name = $data['name'] ?? null;
        $slug = $data['slug'] ?? null;
        $description = $data['description'] ?? null;

        $payload = ['id' => $id, 'name' => $name, 'slug' => $slug, 'description' => $description];

        return WordPressActionHelper::updateTermExecute($id, $taxonomy, $payload, $name, $slug, $description);
    }

    /**
     * Delete a Term by Taxonomy.
     *
     * @param string $taxonomy
     *
     * @return collection
     */
    public function deleteTermByTax($taxonomy)
    {
        $id = $this->nodeInfoProvider->getFieldMapData()['id'] ?? null;

        return WordPressActionHelper::deleteTermExecute(
            $id,
            $taxonomy,
            ['id' => $id],
            __('Deleted successfully', 'bit-pi'),
            __('Failed to delete', 'bit-pi')
        );
    }

    /**
     * Add Category to post.
     *
     * @return collection
     */
    public function addCategoryToPost()
    {
        $postId = $this->nodeInfoProvider->getFieldMapData()['postId'] ?? null;
        $categories = $this->nodeInfoProvider->getFieldMapConfigs('post-category.value') ?? [];
        $append = $this->nodeInfoProvider->getFieldMapConfigs('append.value') ?? false;

        $payload = [
            'post_id'    => $postId,
            'categories' => $categories,
            'append'     => $append
        ];

        if (empty($postId)) {
            return WordPressActionHelper::response(__('Post id is required', 'bit-pi'), $payload, 422);
        }

        if (empty($categories)) {
            return WordPressActionHelper::response(__('Post category is required', 'bit-pi'), $payload, 422);
        }

        return WordPressActionHelper::response(
            wp_set_post_categories($postId, $categories, $append),
            $payload
        );
    }

    /**
     * Check WordPress plugin Activation Status.
     *
     * @return collection
     */
    public function checkPluginActivationStatus()
    {
        $pluginFile = $this->nodeInfoProvider->getFieldMapData()['pluginFile'] ?? null;

        $payload = ['plugin_file' => $pluginFile];

        if (empty($pluginFile)) {
            return WordPressActionHelper::response(__('Plugin File is required', 'bit-pi'), $payload, 422);
        }

        $isActive = is_plugin_active($pluginFile);

        return WordPressActionHelper::response(
            $isActive ? __('Plugin is active.', 'bit-pi') : __('Plugin is not active.', 'bit-pi'),
            $payload
        );
    }

    /**
     * Activate WordPress plugin.
     *
     * @return collection
     */
    public function activatePlugin()
    {
        $pluginFile = $this->nodeInfoProvider->getFieldMapData()['pluginFile'] ?? null;

        $payload = ['plugin_file' => $pluginFile];

        if (empty($pluginFile)) {
            return WordPressActionHelper::response(__('Plugin File is required', 'bit-pi'), $payload, 422);
        }

        if (is_plugin_active($pluginFile)) {
            return WordPressActionHelper::response(__('Plugin is already activated', 'bit-pi'), $payload, 422);
        }

        $activate = activate_plugin($pluginFile);

        if (is_wp_error($activate)) {
            return WordPressActionHelper::response(__('Failed to activate the plugin.', 'bit-pi'), $payload, 500);
        }

        return WordPressActionHelper::response(__('Plugin is successfully activated.', 'bit-pi'), $payload);
    }

    /**
     * Handle user metadata addition.
     *
     * @param int $userId
     * @param bool $addMeta
     * @param array $userMeta
     */
    private function addUserMeta($userId, $addMeta, $userMeta): void
    {
        if (empty($addMeta)) {
            return;
        }

        foreach ($userMeta as $metaKey => $metaValue) {
            if (metadata_exists('user', $userId, $metaKey)) {
                update_user_meta($userId, $metaKey, $metaValue);
            } else {
                add_user_meta($userId, $metaKey, $metaValue);
            }
        }
    }

    /**
     * Conditionally send user notification.
     */
    private function sendEmailNotification(int $userId, string $emailNotify): void
    {
        if (!empty($emailNotify)) {
            wp_new_user_notification($userId, null, $emailNotify);
        }
    }
}
