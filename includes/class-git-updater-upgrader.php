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

            $plugin_slug = $repo_config['plugin'];
            $local_plugin_file = $this->find_plugin_file($plugin_slug);
            if (!$local_plugin_file) {
                Git_Updater_Logger::log("Checking '{$plugin_slug}': Could not find local plugin file.");
                continue;
            }

            $current_version = isset($transient->checked[$local_plugin_file]) ? $transient->checked[$local_plugin_file] : '0.0.0';
            $repo = $repo_config['repo'];

            // Determine branch: use config or default to 'main' if not using releases
            // If user explicitly wants "Releases" we should probably have a flag, but for now we default to branch 'main' 
            // if no specific branch is set, matching "I just want the latest version of main".
            $branch = !empty($repo_config['branch']) ? $repo_config['branch'] : 'main';

            // Check remote version from file
            // path on remote: usually same as basename of local file if inside the root
            $remote_file_path = basename($local_plugin_file);

            // If the plugin is in a subdirectory in the repo (not common but possible), 
            // we'd need more config. Assuming root for now.

            $remote_version = $this->api->get_plugin_version($repo, $remote_file_path, $branch);

            Git_Updater_Logger::log("Checking '{$plugin_slug}' ({$repo}@{$branch}): Local v{$current_version}, Remote v" . ($remote_version ?: 'N/A'));

            if ($remote_version && version_compare($current_version, $remote_version, '<')) {
                // Found update!
                Git_Updater_Logger::log("Update available for '{$plugin_slug}': v{$current_version} -> v{$remote_version}");
                $obj = new stdClass();
                $obj->slug = $local_plugin_file; // WP expects the plugin file path as slug sometimes, or dirname. 
                // Actually for the 'response' array key, it's the file path. 
                // The obj->slug should usually match the dirname or the unique slug.
                $obj->plugin = $local_plugin_file;
                $obj->new_version = $remote_version;
                $obj->url = 'https://github.com/' . $repo;

                // Package URL for branch
                $obj->package = 'https://api.github.com/repos/' . $repo . '/zipball/' . $branch;

                $transient->response[$local_plugin_file] = $obj;
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
