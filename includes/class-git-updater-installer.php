<?php

if (!defined('ABSPATH')) {
    exit;
}

class Git_Updater_Installer
{
    private $api;

    public function __construct($api)
    {
        $this->api = $api;
    }

    public function install($repo, $branch, $target_slug)
    {
        Git_Updater_Logger::log("Starting installation: $repo, Branch: $branch, Target: $target_slug");

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        // 1. Prepare info
        if (empty($branch)) {
            $branch = 'main';
        }

        // Check if target directory already exists
        $target_path = WP_PLUGIN_DIR . '/' . $target_slug;
        if (file_exists($target_path)) {
            Git_Updater_Logger::log("Error: Target folder already exists at $target_path");
            return new WP_Error('folder_exists', 'Target folder already exists: ' . $target_slug);
        }

        // 2. Get Download URL
        // Use zipball URL for branch/tag
        // https://api.github.com/repos/:owner/:repo/zipball/:ref
        $url = 'https://api.github.com/repos/' . $repo . '/zipball/' . $branch;
        Git_Updater_Logger::log("Download URL: $url");

        // 3. Download
        // We need to inject headers manually if private, but download_url doesn't support headers easily natively in older WP 
        // without the 'http_request_args' filter, similar to our Upgrader.
        // Let's reuse the logic: add filter, download, remove filter.

        $this->add_auth_filter();
        $tmp_file = download_url($url);
        $this->remove_auth_filter();

        if (is_wp_error($tmp_file)) {
            Git_Updater_Logger::log("Download failed: " . $tmp_file->get_error_message());
            return $tmp_file;
        }
        Git_Updater_Logger::log("Downloaded to temp file: $tmp_file");

        // 4. Extract
        // Use WP Uploads dir for temp storage to avoid permission issues in system TEMP
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/git-updater-temp-' . uniqid();

        if (!mkdir($temp_dir) && !is_dir($temp_dir)) {
            unlink($tmp_file);
            Git_Updater_Logger::log("Failed to create temp dir: $temp_dir");
            return new WP_Error('mkdir_failed', 'Could not create temp directory.');
        }

        $unzip_result = unzip_file($tmp_file, $temp_dir);

        if (is_wp_error($unzip_result)) {
            Git_Updater_Logger::log("WP unzip_file failed: " . $unzip_result->get_error_message() . ". Trying native ZipArchive.");

            if ($this->unzip_native($tmp_file, $temp_dir)) {
                Git_Updater_Logger::log("Native unzip successful.");
                $unzip_result = true;
            } else {
                unlink($tmp_file);
                Git_Updater_Logger::log("Native unzip also failed.");
                return new WP_Error('unzip_failed', 'Unzip failed via both WP and ZipArchive.');
            }
        }
        unlink($tmp_file); // Clean up zip
        Git_Updater_Logger::log("Unzipped to: $temp_dir");

        // 5. Find the extracted folder
        $files = scandir($temp_dir);

        if ($files === false) {
            Git_Updater_Logger::log("Scandir failed on $temp_dir. Check permissions.");
            return new WP_Error('scandir_failed', 'Could not list files in temp directory.');
        }

        Git_Updater_Logger::log("Scanned temp dir ($temp_dir): " . implode(', ', $files));

        $extracted_folder = null;
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && is_dir($temp_dir . '/' . $file)) {
                $extracted_folder = $file;
                break;
            }
        }

        if (!$extracted_folder) {
            Git_Updater_Logger::log("No extracted folder found in zip.");
            return new WP_Error('unzip_empty', 'No folder found in zip.');
        }
        Git_Updater_Logger::log("Found extracted folder: $extracted_folder");

        // 6. Move/Rename to Target Slug in Filesystem
        global $wp_filesystem;

        $moved = false;

        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            // Suppress output of WP_Filesystem to check status
            ob_start();
            $fs_init = WP_Filesystem();
            ob_end_clean();
        } else {
            $fs_init = true;
        }

        $source = $temp_dir . '/' . $extracted_folder;
        $destination = WP_PLUGIN_DIR . '/' . $target_slug;
        Git_Updater_Logger::log("Attempting move from $source to $destination");

        if ($fs_init && !empty($wp_filesystem) && is_object($wp_filesystem)) {
            // Try WP Filesystem
            Git_Updater_Logger::log("Using WP_Filesystem->move");
            $moved = $wp_filesystem->move($source, $destination);

            // Cleanup temp dir via FS
            if ($moved) {
                $wp_filesystem->delete($temp_dir, true);
            }
        }

        // Fallback to PHP native if WP Filesystem failed or didn't init
        if (!$moved) {
            Git_Updater_Logger::log("WP_Filesystem failed/not available. Trying native rename.");
            if (@rename($source, $destination)) {
                $moved = true;
                Git_Updater_Logger::log("Native rename successful.");
                $this->recursive_rmdir($temp_dir);
            } else {
                // Rename failed, try copy + delete (recursive copy needed)
                Git_Updater_Logger::log("Rename failed. Trying recursive copy.");
                if ($this->recursive_copy($source, $destination)) {
                    $moved = true;
                    Git_Updater_Logger::log("Recursive copy successful.");
                    $this->recursive_rmdir($temp_dir);
                } else {
                    $error = error_get_last();
                    $err_msg = isset($error['message']) ? $error['message'] : 'Unknown error';
                    Git_Updater_Logger::log("Recursive copy failed. Error: $err_msg");
                    return new WP_Error('fs_failed', 'Filesystem failed. Native error: ' . $err_msg);
                }
            }
        }

        if (!$moved) {
            Git_Updater_Logger::log("Move operation failed completely.");
            return new WP_Error('move_failed', 'Could not move plugin to destination.');
        }

        // 7. Register in Settings
        $repos = get_option('git_updater_repos', array());
        if (!is_array($repos)) {
            $repos = array();
        }

        // Check for duplicates
        $exists = false;
        foreach ($repos as $existing_repo) {
            if ($existing_repo['plugin'] === $target_slug && $existing_repo['repo'] === $repo) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $repos[] = array(
                'plugin' => $target_slug, // Or find the main file? Ideally we assume slug is enough for folder mapping
                'repo' => $repo,
                'branch' => $branch
            );
            update_option('git_updater_repos', $repos);
            Git_Updater_Logger::log("Installation successful! Registered $target_slug in settings.");
        } else {
            Git_Updater_Logger::log("Installation successful! Repo $target_slug already registered.");
        }

        return true;
    }

    private function add_auth_filter()
    {
        add_filter('http_request_args', array($this, 'inject_auth_header'), 10, 2);
    }

    private function remove_auth_filter()
    {
        remove_filter('http_request_args', array($this, 'inject_auth_header'));
    }

    public function inject_auth_header($args, $url)
    {
        $token = $this->api->get_token();
        if ($token) {
            $args['headers']['Authorization'] = 'token ' . $token;
        }
        return $args;
    }

    private function recursive_rmdir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursive_rmdir("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    private function recursive_copy($source, $dest)
    {
        if (is_dir($source)) {
            @mkdir($dest);
            $files = scandir($source);
            foreach ($files as $file) {
                if ($file != "." && $file != "..") {
                    $this->recursive_copy("$source/$file", "$dest/$file");
                }
            }
        } elseif (file_exists($source)) {
            copy($source, $dest);
        }
        return file_exists($dest);
    }
    private function unzip_native($file, $to)
    {
        if (!class_exists('ZipArchive')) {
            Git_Updater_Logger::log("ZipArchive class not found.");
            return false;
        }
        $zip = new ZipArchive;
        $res = $zip->open($file);
        if ($res === TRUE) {
            $zip->extractTo($to);
            $zip->close();
            return true;
        }
        Git_Updater_Logger::log("ZipArchive open failed code: " . $res);
        return false;
    }
}
