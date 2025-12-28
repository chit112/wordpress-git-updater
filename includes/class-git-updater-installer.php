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
        // 1. Prepare info
        if (empty($branch)) {
            $branch = 'main';
        }

        // Check if target directory already exists
        $target_path = WP_PLUGIN_DIR . '/' . $target_slug;
        if (file_exists($target_path)) {
            return new WP_Error('folder_exists', 'Target folder already exists: ' . $target_slug);
        }

        // 2. Get Download URL
        // Use zipball URL for branch/tag
        // https://api.github.com/repos/:owner/:repo/zipball/:ref
        $url = 'https://api.github.com/repos/' . $repo . '/zipball/' . $branch;

        // 3. Download
        // We need to inject headers manually if private, but download_url doesn't support headers easily natively in older WP 
        // without the 'http_request_args' filter, similar to our Upgrader.
        // Let's reuse the logic: add filter, download, remove filter.

        $this->add_auth_filter();
        $tmp_file = download_url($url);
        $this->remove_auth_filter();

        if (is_wp_error($tmp_file)) {
            return $tmp_file;
        }

        // 4. Extract
        // Create a temp directory for extraction to avoid cluttering WP_PLUGIN_DIR with "owner-repo-sha" folders
        $temp_dir = get_temp_dir() . 'git-updater-' . uniqid();
        if (!mkdir($temp_dir) && !is_dir($temp_dir)) {
            unlink($tmp_file);
            return new WP_Error('mkdir_failed', 'Could not create temp directory.');
        }

        $unzip_result = unzip_file($tmp_file, $temp_dir);
        unlink($tmp_file); // Clean up zip

        if (is_wp_error($unzip_result)) {
            return $unzip_result;
        }

        // 5. Find the extracted folder
        $files = scandir($temp_dir);
        $extracted_folder = null;
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && is_dir($temp_dir . '/' . $file)) {
                $extracted_folder = $file;
                break;
            }
        }

        if (!$extracted_folder) {
            return new WP_Error('unzip_empty', 'No folder found in zip.');
        }

        // 6. Move/Rename to Target Slug in Filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $source = $temp_dir . '/' . $extracted_folder;
        $destination = WP_PLUGIN_DIR . '/' . $target_slug;

        // Use WP_Filesystem copy/move
        $result = $wp_filesystem->move($source, $destination);

        // Cleanup temp dir
        $wp_filesystem->delete($temp_dir, true);

        if (!$result) {
            return new WP_Error('move_failed', 'Could not move plugin to destination.');
        }

        // 7. Register in Settings
        $repos = get_option('git_updater_repos', array());
        if (!is_array($repos)) {
            $repos = array();
        }

        $repos[] = array(
            'plugin' => $target_slug, // Or find the main file? Ideally we assume slug is enough for folder mapping
            'repo' => $repo,
            'branch' => $branch
        );

        update_option('git_updater_repos', $repos);

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
}
