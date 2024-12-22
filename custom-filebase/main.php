<?php 
// main.php
/*
 * Plugin Name: Custom Filebase with Directory Scanner
 * Plugin URI: https://rebelsofgaming.de/
 * Description: A custom plugin to scan a directory structure and create a filebase with category structure, file viewer, and download functionality in a single shortcode, supporting multiple file types and search functionality.
 * Author: wahke
 * Author URI: https://wahke.lu
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version: 4.2
 * Requires at least: 6.6
 * Requires PHP: 7.4

*/

// Include required files
require_once plugin_dir_path(__FILE__) . 'shared-functions.php';
require_once plugin_dir_path(__FILE__) . 'admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'frontend-display.php';

// Register the custom post type for file-specific comments
function filebase_register_post_type() {
    register_post_type('filebase_comment', [
        'labels' => [
            'name' => __('Filebase Comments'),
            'singular_name' => __('Filebase Comment')
        ],
        'public' => false,
        'show_ui' => true,
        'supports' => ['title', 'editor', 'comments', 'custom-fields'],
        'has_archive' => false,
        'rewrite' => false,
    ]);
}
add_action('init', 'filebase_register_post_type');

// Handle file download
function filebase_handle_download() {
    if (isset($_GET['download']) && $_GET['download'] == 1 && isset($_GET['file'])) {
        $base_path = get_option('filebase_path', '');
        $file_path = realpath($base_path . DIRECTORY_SEPARATOR . sanitize_text_field($_GET['file']));

        if (!$file_path || strpos($file_path, realpath($base_path)) !== 0 || !file_exists($file_path)) {
            wp_die('Invalid file.');
        }

        $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);

        $mime_types = [
            'exe' => 'application/octet-stream',
            'run' => 'application/x-executable',
            'dmg' => 'application/x-apple-diskimage',
            'zip' => 'application/zip'
        ];

        $mime_type = $mime_types[$file_extension] ?? 'application/octet-stream';

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . filesize($file_path));

        readfile($file_path);
        exit;
    }
}
add_action('template_redirect', 'filebase_handle_download');
