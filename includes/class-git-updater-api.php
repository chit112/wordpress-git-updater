<?php

if (!defined('ABSPATH')) {
    exit;
}

class Git_Updater_API
{

    private $token;

    public function __construct()
    {
        $this->token = get_option('git_updater_token');
    }

    public function get_token()
    {
        if (is_array($this->token) && isset($this->token['token'])) {
            return $this->token['token'];
        }
        return is_string($this->token) ? $this->token : '';
    }

    public function get_file_content($repo, $path, $branch)
    {
        // https://api.github.com/repos/:owner/:repo/contents/:path?ref=:branch
        $url = 'https://api.github.com/repos/' . $repo . '/contents/' . $path;
        $url = add_query_arg(array(
            'ref' => $branch,
            't'   => time() // Cache busting
        ), $url);

        $args = array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
            ),
        );

        $token = $this->get_token();
        if ($token) {
            $args['headers']['Authorization'] = 'token ' . $token;
        }

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (isset($data->content) && 'base64' === $data->encoding) {
            return base64_decode($data->content);
        }

        return false;
    }

    public function get_plugin_version($repo, $file_path, $branch)
    {
        $content = $this->get_file_content($repo, $file_path, $branch);
        if (!$content) {
            Git_Updater_Logger::log("Failed to fetch content from GitHub for {$repo}/{$file_path} on branch {$branch}");
            return false;
        }

        if (preg_match('/Version:\s*(\S+)/i', $content, $matches)) {
            Git_Updater_Logger::log("DEBUG: Found version '{$matches[1]}' in {$repo}/{$file_path}. Full match: '{$matches[0]}'");
            return $matches[1];
        }

        Git_Updater_Logger::log("Could not find Version header in content of {$repo}/{$file_path}. Content start: " . substr($content, 0, 100));
        return false;
    }

    public function get_latest_release($repo)
    {
        $url = 'https://api.github.com/repos/' . $repo . '/releases/latest';

        $args = array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
            ),
        );

        $token = $this->get_token();
        if ($token) {
            $args['headers']['Authorization'] = 'token ' . $token;
        }

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (json_last_error() === JSON_ERROR_NONE && !empty($data)) {
            return $data;
        }

        return false;
    }

    /**
     * Get the latest commit SHA for a branch.
     *
     * @param string $repo   Repository in owner/repo format.
     * @param string $branch Branch name.
     * @return string|false  The commit SHA or false on failure.
     */
    public function get_latest_commit_sha($repo, $branch)
    {
        $url = 'https://api.github.com/repos/' . $repo . '/commits/' . $branch;
        $url = add_query_arg('t', time(), $url); // Cache busting

        $args = array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
            ),
        );

        $token = $this->get_token();
        if ($token) {
            $args['headers']['Authorization'] = 'token ' . $token;
        }

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            Git_Updater_Logger::log("Failed to fetch commit SHA for {$repo}@{$branch}: " . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (json_last_error() === JSON_ERROR_NONE && !empty($data->sha)) {
            return $data->sha;
        }

        Git_Updater_Logger::log("Invalid response when fetching commit SHA for {$repo}@{$branch}");
        return false;
    }
}
