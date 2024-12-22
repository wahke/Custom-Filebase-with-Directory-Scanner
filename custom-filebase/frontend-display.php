<?php
// frontend-display.php

// Unified shortcode to display directory structure, file viewer, search, and download functionality
function display_filebase($atts) {
    $atts = shortcode_atts(['path' => ''], $atts);
    $base_path = get_option('filebase_path', '');

    // Retrieve the current path or file from the query parameter
    $current_path = isset($_GET['path']) ? sanitize_text_field($_GET['path']) : '';
    $current_file = isset($_GET['file']) ? sanitize_text_field($_GET['file']) : '';
    $search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    $output = '';

    // Search form
    $output .= '<form method="get" class="filebase-search-form">';
    $output .= '<input type="hidden" name="page_id" value="' . get_the_ID() . '">';
    $output .= '<input type="text" name="search" value="' . esc_attr($search_query) . '" placeholder="Search files or folders...">';
    $output .= '<button type="submit">Search</button>';
    $output .= '</form>';

    // If search query exists, display search results
    if (!empty($search_query)) {
        $output .= '<h2>Search Results for: ' . esc_html($search_query) . '</h2>';
        $output .= filebase_search_results($base_path, $search_query);
        return $output;
    }

    if (!empty($current_path) || !empty($current_file)) {
        $back_link = esc_url(add_query_arg(['path' => dirname($current_path ?: $current_file)], get_permalink()));
        $output .= '<a href="' . $back_link . '" class="filebase-back-button">Go Back</a>';
    }

    if (!empty($current_file)) {
        return $output . filebase_render_file_details($base_path, $current_file);
    }

    $full_path = $base_path;
    if (!empty($current_path)) {
        $full_path .= DIRECTORY_SEPARATOR . $current_path;
    }

    if (empty($base_path) || !is_dir($base_path)) {
        return '<p>Please configure a valid directory path in the settings.</p>';
    }

    $output .= '<div class="filebase">';
    $output .= filebase_generate_tree($full_path, $current_path);
    $output .= '</div>';

    return $output;
}
add_shortcode('filebase', 'display_filebase');

function filebase_generate_tree($path, $relative_path = '') {
    $output = '<ul>';
    $files = scandir($path);

    $current_url = get_permalink();

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $full_path = $path . DIRECTORY_SEPARATOR . $file;
        $relative = $relative_path ? $relative_path . DIRECTORY_SEPARATOR . $file : $file;

        if (is_dir($full_path)) {
            $output .= '<li class="folder">';
            $output .= '<a href="' . esc_url(add_query_arg('path', urlencode($relative), $current_url)) . '">' . esc_html($file) . '</a>';
            $output .= '</li>';
        } else {
            $file_size = filebase_format_file_size(filesize($full_path));
            $file_details_url = esc_url(add_query_arg(['file' => urlencode($relative)], $current_url));

            $thumbnail = filebase_get_thumbnail($relative);

            $output .= '<li class="file">';
            if ($thumbnail) {
                $output .= '<img src="' . esc_url($thumbnail) . '" alt="Thumbnail" style="max-width:50px; margin-right:10px; vertical-align:middle;">';
            }
            $output .= '<a href="' . $file_details_url . '">' . esc_html($file) . '</a> (' . $file_size . ')';
            $output .= '</li>';
        }
    }

    $output .= '</ul>';
    return $output;
}

function filebase_format_file_size($size_in_bytes) {
    if ($size_in_bytes >= 1073741824) {
        return round($size_in_bytes / 1073741824, 2) . ' GB';
    } elseif ($size_in_bytes >= 1048576) {
        return round($size_in_bytes / 1048576, 2) . ' MB';
    } elseif ($size_in_bytes >= 1024) {
        return round($size_in_bytes / 1024, 2) . ' KB';
    } else {
        return $size_in_bytes . ' Bytes';
    }
}

function filebase_search_results($base_path, $query) {
    if (!function_exists('filebase_get_all_files')) {
        require_once plugin_dir_path(__FILE__) . 'shared-functions.php';
    }

    $results = filebase_search_recursive($base_path, $query);

    if (empty($results)) {
        return '<p>No results found.</p>';
    }

    $output = '<ul>';
    $current_url = get_permalink();

    foreach ($results as $result) {
        $relative_path = str_replace($base_path . DIRECTORY_SEPARATOR, '', $result);
        if (is_dir($result)) {
            $output .= '<li class="folder">';
            $output .= '<a href="' . esc_url(add_query_arg('path', urlencode($relative_path), $current_url)) . '">' . esc_html($relative_path) . '</a>';
            $output .= '</li>';
        } else {
            $output .= '<li class="file">';
            $output .= '<a href="' . esc_url(add_query_arg(['file' => urlencode($relative_path)], $current_url)) . '">' . esc_html($relative_path) . '</a>';
            $output .= '</li>';
        }
    }
    $output .= '</ul>';

    return $output;
}

function filebase_get_thumbnail($relative_path) {
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

    if (!empty($existing_post)) {
        return get_post_meta($existing_post[0]->ID, '_filebase_thumbnail', true);
    }

    return null;
}

function filebase_render_file_details($base_path, $relative_file) {
    $file_path = realpath($base_path . DIRECTORY_SEPARATOR . $relative_file);

    if (!$file_path || strpos($file_path, realpath($base_path)) !== 0 || !file_exists($file_path)) {
        return '<p>Invalid file.</p>';
    }

    $post_id = filebase_get_or_create_post($relative_file);

    $post = get_post($post_id);
    $description = $post ? $post->post_content : 'No description available.';
    $thumbnail = get_post_meta($post_id, '_filebase_thumbnail', true);

    $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);
    $download_link = esc_url(home_url('/') . '?file=' . urlencode($relative_file) . '&download=1');

    $output = '<div style="text-align:center;">';
    $output .= '<h1>' . esc_html(basename($file_path)) . '</h1>';
    if (!empty($thumbnail)) {
        $output .= '<img src="' . esc_url($thumbnail) . '" alt="Thumbnail" style="max-width:300px; margin:10px auto; display:block;">';
    }
    $output .= '<p><a href="' . $download_link . '" class="button">Download</a></p>';
    $output .= '<h2>Description:</h2>';
    $output .= '<div>' . wp_kses_post($description) . '</div>';

    if (in_array($file_extension, ['zip'])) {
        $output .= '<h2>Contents:</h2><pre style="text-align:left; margin:auto; overflow:auto; background:#f4f4f4; padding:10px;">';

        $zip = new ZipArchive;
        if ($zip->open($file_path) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $output .= esc_html($zip->getNameIndex($i)) . "\n";
            }
            $zip->close();
        } else {
            $output .= 'Unable to read zip contents.';
        }

        $output .= '</pre>';
    } elseif (in_array($file_extension, ['txt', 'log', 'json', 'xml'])) {
        $output .= '<h2>File Contents:</h2><pre style="text-align:left; margin:auto; overflow:auto; background:#f4f4f4; padding:10px;">';
        $output .= esc_html(file_get_contents($file_path));
        $output .= '</pre>';
    } else {
        $output .= '<p>Preview not available for this file type.</p>';
    }

    $output .= '</div><br />';
    return $output;
}

function filebase_get_or_create_post($relative_file) {
    $existing_post = get_posts([
        'post_type' => 'filebase_comment',
        'meta_query' => [
            [
                'key' => '_filebase_file',
                'value' => $relative_file,
                'compare' => '=',
            ],
        ],
        'posts_per_page' => 1,
    ]);

    if (!empty($existing_post)) {
        return $existing_post[0]->ID;
    }

    $post_id = wp_insert_post([
        'post_title' => 'Comments for ' . $relative_file,
        'post_type' => 'filebase_comment',
        'post_status' => 'publish',
        'meta_input' => [
            '_filebase_file' => $relative_file,
        ],
    ]);

    return $post_id;
}
