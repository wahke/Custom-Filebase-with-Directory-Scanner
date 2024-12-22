<?php
// shared-functions.php

function filebase_get_all_files($path) {
    $results = [];

    if (!is_readable($path)) {
        error_log('Filebase: Directory not readable: ' . $path);
        return $results;
    }

    $files = scandir($path);
    error_log('Filebase: Scanning directory: ' . $path);

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $full_path = $path . DIRECTORY_SEPARATOR . $file;

        if (is_dir($full_path)) {
            $results = array_merge($results, filebase_get_all_files($full_path));
        } elseif (is_file($full_path)) {
            $results[] = $full_path;
            error_log('Filebase: File found: ' . $full_path);
        }
    }

    return $results;
}

function filebase_search_recursive($path, $query) {
    $results = [];
    $files = scandir($path);

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $full_path = $path . DIRECTORY_SEPARATOR . $file;

        if (is_dir($full_path)) {
            $results = array_merge($results, filebase_search_recursive($full_path, $query));
        }

        if (stripos($file, $query) !== false) {
            $results[] = $full_path;
        }
    }

    return $results;
}
