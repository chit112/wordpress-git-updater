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
        return $this->token;
    }

    public function get_file_content($repo, $path, $branch)
    {
        // https://api.github.com/repos/:owner/:repo/contents/:path?ref=:branch
        $url = 'https://api.github.com/repos/' . $repo . '/contents/' . $path;
        $url = add_query_arg('ref', $branch, $url);

        $args = array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
            ),
        );

        if ($this->token) {
            $args['headers']['Authorization'] = 'token ' . $this->token;
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
            return false;
        }

        if (preg_match('/Version:\s*(\S+)/i', $content, $matches)) {
            return $matches[1];
        }

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

        if ($this->token) {
            $args['headers']['Authorization'] = 'token ' . $this->token;
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
}
