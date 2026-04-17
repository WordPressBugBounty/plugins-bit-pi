<?php

namespace BitApps\Pi\src\Integrations\WordPress;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

class WordPressTasks
{
    private const APP_SLUG = 'wordPress';

    /**
     * WORDPRESS_HOOK_LIST.
     *
     * This constant contains a list of WordPress hooks with their corresponding number of accepted arguments.
     *
     * Each key represents the name of a WordPress hook, and the associated value specifies the number of arguments
     * that the hook expects when triggered.
     *
     * @varant array WORDPRESS_HOOK_LIST
     */
    private const WORDPRESS_HOOK_LIST = [
        '_wp_put_post_revision' => [
            'args' => 2
        ],
        'activated_plugin' => [
            'args' => 2
        ],
        'add_attachment' => [
            'args' => 2
        ],
        'add_meta_boxes' => [
            'args' => 2
        ],
        'added_option' => [
            'args' => 2
        ],
        'admin_post_' => [
            'args' => 2
        ],
        'after_password_reset' => [
            'args' => 2
        ],
        'attachment_fields_to_edit' => [
            'args' => 2
        ],
        'attachment_fields_to_save' => [
            'args' => 2
        ],
        'attachment_updated' => [
            'args' => 3
        ],
        'before_delete_post' => [
            'args' => 2
        ],
        'wp_insert_comment' => [
            'args' => 2
        ],
        'wp_insert_post' => [
            'args' => 3
        ],
        'comment_post' => [
            'args' => 3
        ],
        'create_term' => [
            'args' => 4
        ],
        'created_term' => [
            'args' => 4
        ],
        'customize_register' => [

        ],
        'deactivated_plugin' => [
            'args' => 2
        ],
        'delete_attachment' => [
            'args' => 2
        ],
        'delete_comment' => [
            'args' => 2
        ],
        'delete_option' => [

        ],
        'delete_post' => [
            'args' => 2
        ],
        'wp_insert_site' => [

        ],
        'delete_site' => [

        ],
        'delete_term' => [
            'args' => 5
        ],
        'delete_term_taxonomy' => [

        ],
        'delete_user' => [
            'args' => 3
        ],
        'deleted_option' => [

        ],
        'edit_attachment' => [

        ],
        'edit_comment' => [
            'args' => 2
        ],
        'edit_term' => [
            'args' => 4
        ],
        'edit_terms' => [
            'args' => 3
        ],
        'edit_user' => [

        ],
        'edit_user_profile' => [

        ],
        'edit_user_profile_update' => [

        ],
        'edited_term' => [
            'args' => 4
        ],
        'generate_rewrite_rules' => [

        ],
        'image_size_names_choose' => [

        ],
        'login_footer' => [

        ],
        'login_form' => [

        ],
        'login_head' => [

        ],
        'login_init' => [

        ],
        'lostpassword_form' => [

        ],
        'media_upload_tabs' => [

        ],
        'password_reset' => [
            'args' => 2
        ],
        'personal_options_update' => [

        ],
        'post_updated' => [
            'args' => 3
        ],
        'pre_comment_approved' => [
            'args' => 2
        ],
        'profile_update' => [
            'args' => 3
        ],
        'register_form' => [

        ],
        'remove_user_from_blog' => [
            'args' => 3
        ],
        'rest_api_init' => [

        ],
        'retrieve_password' => [

        ],
        'save_post' => [
            'args' => 3
        ],
        'set_user_role' => [
            'args' => 3
        ],
        'show_user_profile' => [

        ],
        'signup_blogform' => [

        ],
        'signup_extra_fields' => [

        ],
        'signup_finished' => [

        ],
        'signup_header' => [

        ],
        'switch_blog' => [
            'args' => 3
        ],
        'switch_theme' => [
            'args' => 3
        ],
        'transition_comment_status' => [
            'args' => 3
        ],
        'trashed_comment' => [
            'args' => 2
        ],
        'untrash_post' => [
            'args' => 2
        ],
        'untrashed_comment' => [
            'args' => 2
        ],
        'update_blog_public' => [
            'args' => 2
        ],
        'update_blog_status' => [
            'args' => 3
        ],
        'updated_option' => [
            'args' => 3
        ],
        'upgrader_process_complete' => [

        ],
        'user_register' => [
            'args' => 2
        ],
        'validate_password_reset' => [
            'args' => 2
        ],
        'wp_after_insert_post' => [
            'args' => 4
        ],
        'wp_authenticate' => [
            'args' => 2
        ],
        'wp_count_attachments' => [
            'args' => 2
        ],
        'wp_create_application_password' => [
            'args' => 4
        ],
        'wp_delete_application_password' => [
            'args' => 2
        ],
        'wp_delete_user' => [
            'args' => 2
        ],
        'wp_generate_attachment_metadata' => [
            'args' => 2
        ],
        'wp_login_failed' => [
            'args' => 2
        ],
        'wp_logout' => [

        ],
        'wp_set_comment_status' => [
            'args' => 2
        ],
        'wp_trash_post' => [
            'args' => 2
        ],
        'wp_update_application_password' => [
            'args' => 3
        ],
        'wp_update_comment_count' => [
            'args' => 3
        ],
        'wp_update_user' => [
            'args' => 3
        ],
        'wpmu_activate_user' => [
            'args' => 3
        ],
        'wpmu_delete_user' => [
            'args' => 2
        ],
        'wpmu_new_blog' => [
            'args' => 6
        ],
        'wpmu_new_user' => [

        ],
        'wp_login' => [
            'args' => 2
        ],
        'update_option' => [
            'args' => 3
        ],
    ];

    public static function getHookList()
    {
        return self::WORDPRESS_HOOK_LIST;
    }

    public static function getAppSlug()
    {
        return self::APP_SLUG;
    }
}
