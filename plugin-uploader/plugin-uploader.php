<?php
/*
Plugin Name: Plugin Uploader
Plugin URI: 
Description: Upload zip
Version: 1.0.0
Author: TSPL
Author URI: 
*/

add_action('rest_api_init', function () {
    register_rest_route('api/v1', '/plugin-file', array(
        'methods' => 'POST',
        'callback' => 'handle_file_upload',
        'args' => array(
            'file' => array(
                // 'required' => true,
                'type' => 'file',
            ),
            'type' => array(
                'required' => true,
                'type' => 'string',
            ),
        )
    ));
});

function handle_file_upload($request) {
    include_once(ABSPATH.'wp-admin/includes/plugin.php');
    $file = $request->get_file_params();
    $type = $request->get_param('type');
    if (empty($file)) {
        return new WP_Error('no_file', 'No file submitted', array('status' => 400));
    }

    $file_url = $file['file'];
    $file_name = pathinfo($file_url['name'], PATHINFO_FILENAME);
    
    $upload_dir = wp_upload_dir();
    $zip_path = $upload_dir['basedir'] . '/'.$file_url['name']; 

    move_uploaded_file($file_url['tmp_name'], $zip_path);

    $zip = new ZipArchive;
    if ($zip->open($zip_path) === TRUE) {
        $extract_path = $upload_dir['basedir'] . '/extracted_plugin/';

        $zip->extractTo($extract_path);
        $zip->close();
        $plugin_dir = WP_PLUGIN_DIR . '/' . $file_name;
        $response = '';
        if (is_dir($extract_path . $file_name)) {
            $result = copy_plugin_dir($extract_path . $file_name, $plugin_dir);
            if ($result !== false) {
               $response = plugin_activate_deactivate($plugin_dir, $file_name, $type);
            } else {
                echo 'Failed to copy plugin files to the plugins directory.';
            }
        } else {
            $response = upload_plugin_zip_to_directory($zip_path, $file_name, $type);
        }
        unlink($zip_path);
        delete_dir($extract_path);
        return $response;
    } else {
        echo 'Failed to extract the uploaded zip file.';
    }
}

function delete_dir($dir) {
    if (!is_dir($dir)) return;
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                delete_dir($path);
            } else {
                unlink($path);
            }
        }
    }
    rmdir($dir);
}

function copy_plugin_dir($src, $dst) {
    if (!is_dir($src)) return false;
    if (!is_dir($dst)) mkdir($dst, 0755, true);
    $files = scandir($src);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $src_file = $src . '/' . $file;
            $dst_file = $dst . '/' . $file;
            if (is_dir($src_file)) {
                copy_plugin_dir($src_file, $dst_file);
            } else {
                copy($src_file, $dst_file);
            }
        }
    }
    return true;
}

function upload_plugin_zip_to_directory($plugin_zip_path, $file_name, $type) {
    include_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();

    if ( ! function_exists( 'WP_Filesystem' ) ) {
        return new WP_Error('filesystem_error', 'Unable to access the filesystem', array('status' => 500));
    }

    global $wp_filesystem;

    if ( ! file_exists($plugin_zip_path) ) {
        return new WP_Error('file_not_found', 'Plugin file not found', array('status' => 404));
    }

    $plugin_directory = WP_PLUGIN_DIR . '/'.$file_name;

    if ( ! file_exists($plugin_directory) ) {
        $wp_filesystem->mkdir($plugin_directory);
    }

    $unzip_result = unzip_file($plugin_zip_path, $plugin_directory);

    if ( is_wp_error($unzip_result) ) {
        return $unzip_result; 
    }
    
    return plugin_activate_deactivate($plugin_directory, $file_name, $type);
}

function plugin_activate_deactivate($plugin_directory, $file_name, $type) {
    include_once(ABSPATH.'wp-admin/includes/plugin.php');
    $extracted_files = scandir($plugin_directory);
    
    $plugin_file = '';
    foreach ($extracted_files as $file) {
        if (substr($file, -4) === '.php') {
            $file_content = file_get_contents($plugin_directory.'/' . $file);
            if (preg_match('/Plugin Name:/i', $file_content)) {
                $plugin_file = $file;
                break;
            }
        }
    }

    if (!empty($plugin_file)) {
        $path = $file_name.'/'.$plugin_file;
        
        if($type == "ACTIVE") {
            if (!is_plugin_active($path)) {
                activate_plugin($path);
            }
            return 'Plugin uploaded & activated successfully!';
        } elseif($type == "DEACTIVATE") {
            if(!is_plugin_inactive($path)) {
                deactivate_plugins($path);
            }
            return 'Plugin uploaded & deactivated successfully!';
        } else {
            return 'Plugin uploaded successfully!';
        }

    } else {
        return new WP_Error('no_main_file', 'Main plugin file not found', array('status' => 404));
    }
}


?>