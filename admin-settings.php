<?php
// admin-settings.php

// Add settings page to define the directory path
function filebase_add_settings_page() {
    add_options_page(
        'Filebase Settings',
        'Filebase',
        'manage_options',
        'filebase-settings',
        'filebase_settings_page'
    );
}
add_action('admin_menu', 'filebase_add_settings_page');

function filebase_settings_page() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['filebase_path'])) {
            update_option('filebase_path', sanitize_text_field($_POST['filebase_path']));
            echo '<div class="updated"><p>Path updated successfully!</p></div>';
        }
        if (isset($_POST['filebase_default_thumbnail'])) {
            update_option('filebase_default_thumbnail', esc_url_raw($_POST['filebase_default_thumbnail']));
            echo '<div class="updated"><p>Default thumbnail updated successfully!</p></div>';
        }
    }

    $path = get_option('filebase_path', '');
    $default_thumbnail = get_option('filebase_default_thumbnail', '');

    echo '<div class="wrap">';
    echo '<h1>Filebase Settings</h1>';
    echo '<form method="post">';
    echo '<table class="form-table">';

    // Path setting
    echo '<tr valign="top">';
    echo '<th scope="row"><label for="filebase_path">Base Directory Path</label></th>';
    echo '<td><input type="text" id="filebase_path" name="filebase_path" value="' . esc_attr($path) . '" class="regular-text"></td>';
    echo '</tr>';

    // Default thumbnail setting
    echo '<tr valign="top">';
    echo '<th scope="row"><label for="filebase_default_thumbnail">Default Thumbnail URL</label></th>';
    echo '<td><input type="text" id="filebase_default_thumbnail" name="filebase_default_thumbnail" value="' . esc_attr($default_thumbnail) . '" class="regular-text">';
    echo '<p class="description">Enter the URL of the default thumbnail image to be used when no specific thumbnail is set for a file.</p></td>';
    echo '</tr>';

    echo '</table>';
    echo '<p class="submit"><input type="submit" class="button-primary" value="Save Changes"></p>';
    echo '</form>';
    echo '</div>';
}

// Add Sync Files submenu under Filebase Comments
function filebase_add_sync_button_page() {
    add_submenu_page(
        'edit.php?post_type=filebase_comment', // Parent menu slug
        'Sync Files', // Page title
        'Sync Files', // Menu title
        'manage_options', // Capability
        'sync-files', // Menu slug
        'filebase_render_sync_button_page' // Callback function
    );
}
add_action('admin_menu', 'filebase_add_sync_button_page');

// Render the Sync Files page
function filebase_render_sync_button_page() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sync_result = filebase_sync_files_to_admin();
        if ($sync_result) {
            echo '<div class="updated"><p>Files synchronized successfully!</p></div>';
        } else {
            echo '<div class="error"><p>No new files were added or an error occurred.</p></div>';
        }
    }

    echo '<div class="wrap">';
    echo '<h1>Synchronize Files</h1>';
    echo '<form method="post">';
    echo '<p>Click the button below to scan the filebase directory and add any new files to the admin interface.</p>';
    echo '<button type="submit" class="button button-primary">Sync Files</button>';
    echo '</form>';
    echo '</div>';
}

// Sync files to admin
function filebase_sync_files_to_admin() {
    $base_path = ABSPATH . get_option('filebase_path', '');

    if (empty($base_path) || !is_dir($base_path)) {
        error_log('Filebase sync failed: Invalid base path: ' . $base_path);
        return false; // Exit if the path is invalid
    }

    if (!function_exists('filebase_get_all_files')) {
        require_once plugin_dir_path(__FILE__) . 'shared-functions.php';
    }

    $files = filebase_get_all_files($base_path);
    $added_count = 0;

    foreach ($files as $file) {
        $relative_path = str_replace($base_path . DIRECTORY_SEPARATOR, '', $file);

        // Check if a post for this file already exists
        $existing_post = get_posts([
            'post_type' => 'filebase_comment',
            'meta_query' => [
                [
                    'key' => '_filebase_file',
                    'value' => $relative_path,
                    'compare' => '=',
                ],
            ],
            'posts_per_page' => 1,
        ]);

        if (empty($existing_post)) {
            error_log('Filebase: Adding file: ' . $relative_path);
            // Create a new post
            $result = wp_insert_post([
                'post_title' => basename($relative_path),
                'post_type' => 'filebase_comment',
                'post_status' => 'publish',
                'meta_input' => [
                    '_filebase_file' => $relative_path,
                ],
            ]);

            if ($result) {
                error_log('Filebase: File added successfully: ' . $relative_path);
                $added_count++;
            } else {
                error_log('Filebase: Failed to add file: ' . $relative_path);
            }
        } else {
            error_log('Filebase: File already exists: ' . $relative_path);
        }
    }

    return $added_count > 0;
}

// Add thumbnail meta box for filebase_comment post type
function filebase_add_thumbnail_meta_box() {
    add_meta_box(
        'filebase_thumbnail_meta',
        __('File Thumbnail', 'filebase'),
        'filebase_thumbnail_meta_box_callback',
        'filebase_comment', // Post type
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'filebase_add_thumbnail_meta_box');

// Callback for rendering the thumbnail meta box
function filebase_thumbnail_meta_box_callback($post) {
    // Retrieve the current thumbnail URL
    $thumbnail = get_post_meta($post->ID, '_filebase_thumbnail', true);

    echo '<label for="filebase_thumbnail">' . __('Thumbnail URL:', 'filebase') . '</label>';
    echo '<input type="text" id="filebase_thumbnail" name="filebase_thumbnail" value="' . esc_attr($thumbnail) . '" style="width:100%;">';
    echo '<p class="description">' . __('Enter the URL of the thumbnail image for this file.', 'filebase') . '</p>';
}

// Save the thumbnail URL when the post is saved
function filebase_save_thumbnail_meta_box($post_id) {
    if (array_key_exists('filebase_thumbnail', $_POST)) {
        update_post_meta(
            $post_id,
            '_filebase_thumbnail',
            esc_url_raw($_POST['filebase_thumbnail'])
        );
    }
}
add_action('save_post', 'filebase_save_thumbnail_meta_box');
