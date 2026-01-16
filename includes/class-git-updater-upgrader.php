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
        // Update stored SHA after successful upgrade
        add_action('upgrader_process_complete', array($this, 'update_stored_sha_after_upgrade'), 10, 2);
    }

    public function check_for_updates($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $repos = get_option('git_updater_repos', array());

        foreach ($repos as $index => $repo_config) {
            if (empty($repo_config['plugin']) || empty($repo_config['repo'])) {
                continue;
            }

            $plugin_slug = $repo_config['plugin'];
            $local_plugin_file = $this->find_plugin_file($plugin_slug);
            if (!$local_plugin_file) {
                Git_Updater_Logger::log("Checking '{$plugin_slug}': Could not find local plugin file.");
                continue;
            }

            $repo = $repo_config['repo'];
            $branch = !empty($repo_config['branch']) ? $repo_config['branch'] : 'main';
            $stored_sha = !empty($repo_config['commit_sha']) ? $repo_config['commit_sha'] : '';

            // Fetch latest commit SHA from GitHub
            $remote_sha = $this->api->get_latest_commit_sha($repo, $branch);

            if (!$remote_sha) {
                Git_Updater_Logger::log("Checking '{$plugin_slug}': Could not fetch remote SHA.");
                continue;
            }

            $short_stored = $stored_sha ? substr($stored_sha, 0, 7) : 'unknown';
            $short_remote = substr($remote_sha, 0, 7);

            Git_Updater_Logger::log("Checking '{$plugin_slug}' ({$repo}@{$branch}): Local SHA {$short_stored}, Remote SHA {$short_remote}");

            // Compare SHAs - if different, update is available
            if ($stored_sha !== $remote_sha) {
                Git_Updater_Logger::log("Update available for '{$plugin_slug}': {$short_stored} -> {$short_remote}");

                $obj = new stdClass();
                $obj->slug = dirname($local_plugin_file);
                $obj->plugin = $local_plugin_file;
                $obj->new_version = $short_remote; // Display short SHA as version
                $obj->url = 'https://github.com/' . $repo;
                $obj->package = 'https://api.github.com/repos/' . $repo . '/zipball/' . $branch;

                // Store the new SHA so we can update it after successful upgrade
                $obj->git_updater_new_sha = $remote_sha;
                $obj->git_updater_repo_index = $index;

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

    /**
     * Update the stored commit SHA after a successful plugin upgrade.
     *
     * @param WP_Upgrader $upgrader Upgrader instance.
     * @param array       $options  Update options.
     */
    public function update_stored_sha_after_upgrade($upgrader, $options)
    {
        // Only handle plugin updates
        if ($options['action'] !== 'update' || $options['type'] !== 'plugin') {
            return;
        }

        if (empty($options['plugins'])) {
            return;
        }

        $repos = get_option('git_updater_repos', array());
        $updated = false;

        foreach ($options['plugins'] as $plugin_file) {
            // Find this plugin in our monitored repos
            foreach ($repos as $index => $repo_config) {
                $local_plugin_file = $this->find_plugin_file($repo_config['plugin']);

                if ($local_plugin_file === $plugin_file) {
                    // This is one of our monitored plugins - fetch and store the new SHA
                    $branch = !empty($repo_config['branch']) ? $repo_config['branch'] : 'main';
                    $new_sha = $this->api->get_latest_commit_sha($repo_config['repo'], $branch);

                    if ($new_sha) {
                        $repos[$index]['commit_sha'] = $new_sha;
                        $updated = true;
                        Git_Updater_Logger::log("Updated stored SHA for '{$repo_config['plugin']}' to " . substr($new_sha, 0, 7));
                    }
                    break;
                }
            }
        }

        if ($updated) {
            update_option('git_updater_repos', $repos);
        }
    }
}
