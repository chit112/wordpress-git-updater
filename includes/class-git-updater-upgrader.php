<?php

if (!defined('ABSPATH')) {
    exit;
}

class Git_Updater_Upgrader
{

    private $settings;
    private $api;

    public function __construct($settings, $api)
    {
        $this->settings = $settings;
        $this->api = $api;

        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
        // Need to hook into installation process to handle authentication for private repos download
        // This is complex in WP. For private repos, the download URL is usually not directly accessible without headers.
        add_filter('upgrader_pre_download', array($this, 'add_download_headers'), 10, 3);
    }

    public function check_for_updates($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $repos = get_option('git_updater_repos', array());

        foreach ($repos as $repo_config) {
            if (empty($repo_config['plugin']) || empty($repo_config['repo'])) {
                continue;
            }

            $plugin_slug = $repo_config['plugin']; // This typically needs to be plugin-folder/plugin-file.php for WP to recognize it? 
            // Actually, $transient->checked uses "folder/file.php".
            // So our setting should ideally capture that, or we scan the dir to find the file.
            // For now, let's assume the user enters "folder/file.php" or we approximate.
            // Simplification: We iterate over $transient->checked (which are local plugins) and see if any match our "folder" configuration.

            $local_plugin_file = $this->find_plugin_file($plugin_slug);
            if (!$local_plugin_file) {
                continue;
            }

            // Check if this plugin is in our list
            // $transient->checked[$local_plugin_file] is the version.

            $remote_info = $this->api->get_latest_release($repo_config['repo']);

            if ($remote_info && isset($remote_info->tag_name)) {
                $new_version = ltrim($remote_info->tag_name, 'v');
                $current_version = isset($transient->checked[$local_plugin_file]) ? $transient->checked[$local_plugin_file] : '0.0.0';

                if (version_compare($current_version, $new_version, '<')) {
                    // Found update!
                    $obj = new stdClass();
                    $obj->slug = $plugin_slug; // Or dirname?
                    $obj->plugin = $local_plugin_file;
                    $obj->new_version = $new_version;
                    $obj->url = $remote_info->html_url;

                    // Find zipball
                    $package = $remote_info->zipball_url; // Default code zip
                    // Or assets... check if there is a plugin zip asset?
                    if (!empty($remote_info->assets)) {
                        foreach ($remote_info->assets as $asset) {
                            if (strpos($asset->name, '.zip') !== false) {
                                $package = $asset->browser_download_url; // Or api url? For private repos browser_download_url might work if logged in, but for API...
                                // For private repos, we often need to use the API URL with Accept header: application/octet-stream
                                // But WP's upgrader just takes a URL.
                                // We'll see how to handle this.
                                break;
                            }
                        }
                    }

                    $obj->package = $package;

                    $transient->response[$local_plugin_file] = $obj;
                }
            }
        }

        return $transient;
    }

    private function find_plugin_file($directory_slug)
    {
        // Simple search: check if user provided "folder/file.php" or just "folder"
        if (strpos($directory_slug, '.php') !== false) {
            return $directory_slug;
        }

        // If just folder, try to guess or scan
        // This runs often, so scanning might be slow. 
        // Best requirement: User inputs "folder/file.php"
        // But let's try to be smart if we can, or just return false if not found.
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins = get_plugins();
        foreach ($all_plugins as $path => $data) {
            if (strpos($path, $directory_slug . '/') === 0) {
                return $path;
            }
        }
        return false;
    }

    public function add_download_headers($return, $package, $upgrader)
    {
        // This filter (upgrader_pre_download) allows us to return a custom file path or error, 
        // but it doesn't easily let us change headers for the DEFAULT download process 
        // unless we handle the download ourselves here.

        // A better approach for adding headers to the internal WP download request is `http_request_args`.
        // We'll add that filter temporarily when an update is triggered for our plugins.

        add_filter('http_request_args', array($this, 'inject_auth_header'), 10, 2);

        return $return;
    }

    public function inject_auth_header($args, $url)
    {
        // Check if this URL is one of our GitHub asset URLs.
        // We can do this by checking if it matches github.com and we have a token.
        // Ideally checking against the specific URLs we injected would be safer, 
        // but for now verifying it's a GitHub API or codeload URL is a start.

        $token = $this->api->get_token();
        if (!$token) {
            return $args;
        }

        // Check host
        $host = parse_url($url, PHP_URL_HOST);
        if ('api.github.com' === $host || 'codeload.github.com' === $host) {
            // Inject token
            $args['headers']['Authorization'] = 'token ' . $token;
            // Also need Accept header for assets if using API url, but codeload is standard zip.
        }

        return $args;
    }
}
