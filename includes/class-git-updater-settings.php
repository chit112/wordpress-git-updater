<?php

if (!defined('ABSPATH')) {
    exit;
}

class Git_Updater_Settings
{

    private $installer;

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_git_updater_install', array($this, 'handle_install'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function enqueue_scripts($hook)
    {
        if (isset($_GET['page']) && $_GET['page'] === 'git-updater') {
            wp_enqueue_script('git-updater-settings', GIT_UPDATER_PLUGIN_URL . 'assets/settings.js', array(), '1.0', true);
        }
    }

    public function set_installer($installer)
    {
        $this->installer = $installer;
    }

    public function add_admin_menu()
    {
        add_options_page(
            'Git Updater Settings',
            'Git Updater',
            'manage_options',
            'git-updater',
            array($this, 'display_settings_page')
        );
    }

    public function register_settings()
    {
        register_setting('git_updater_options', 'git_updater_token');
        register_setting('git_updater_options', 'git_updater_repos', array('type' => 'array'));

        add_settings_section(
            'git_updater_main_section',
            'Main Settings',
            null,
            'git-updater'
        );

        add_settings_field(
            'git_updater_token',
            'GitHub Personal Access Token',
            array($this, 'render_token_field'),
            'git-updater',
            'git_updater_main_section'
        );

        add_settings_field(
            'git_updater_repos',
            'Repositories Map',
            array($this, 'render_repos_field'),
            'git-updater',
            'git_updater_main_section'
        );
    }

    public function display_settings_page()
    {
        ?>
        <div class="wrap">
            <h1>Git Updater Settings</h1>

            <?php if (isset($_GET['install_status'])): ?>
                <?php if ($_GET['install_status'] === 'success'): ?>
                    <div class="notice notice-success is-dismissible">
                        <p>Plugin installed successfully!</p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-error is-dismissible">
                        <p>Installation failed:
                            <?php echo isset($_GET['message']) ? esc_html(urldecode($_GET['message'])) : 'Unknown error'; ?>
                        </p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('git_updater_options');
                do_settings_sections('git-updater');
                submit_button();
                ?>
            </form>

            <h2>Installation Logs</h2>
            <?php
            if (isset($_POST['btn_clear_logs'])) {
                if (current_user_can('manage_options')) {
                    Git_Updater_Logger::clear();
                    echo '<div class="notice notice-success is-dismissible"><p>Logs cleared.</p></div>';
                }
            }
            $logs = Git_Updater_Logger::get_logs();
            $log_content = implode("\n", $logs);
            ?>
            <form method="post">
                <textarea class="large-text code" rows="15" readonly><?php echo esc_textarea($log_content); ?></textarea>
                <p>
                    <input type="submit" name="btn_clear_logs" class="button" value="Clear Logs" />
                </p>
            </form>

            <hr>

            <h2>Install New Plugin</h2>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="git_updater_install">
                <?php wp_nonce_field('git_updater_install_nonce', 'git_updater_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">GitHub Repository</th>
                        <td>
                            <input type="text" name="repo" placeholder="owner/repo" class="regular-text" required>
                            <p class="description">e.g. <code>Let-s-Roll/wordpress-admin-ui</code></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Branch/Tag</th>
                        <td>
                            <input type="text" name="branch" placeholder="main" class="regular-text">
                            <p class="description">Leave empty for <code>main</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Target Folder Name</th>
                        <td>
                            <input type="text" name="slug" placeholder="plugin-slug" class="regular-text" required>
                            <p class="description">The folder name in <code>wp-content/plugins</code>.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Install Plugin'); ?>
            </form>
        </div>
        <?php
    }

    public function handle_install()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('git_updater_install_nonce', 'git_updater_nonce');

        $repo = sanitize_text_field($_POST['repo']);

        // Clean up repo input if user pasted full URL
        $repo = preg_replace('/^(?:https?:\/\/)?(?:www\.)?github\.com\//', '', $repo);
        $repo = preg_replace('/\.git$/', '', $repo);
        $repo = trim($repo, '/');

        $branch = sanitize_text_field($_POST['branch']);
        $slug = sanitize_text_field($_POST['slug']);

        if (empty($repo) || empty($slug)) {
            wp_redirect(add_query_arg(array('page' => 'git-updater', 'install_status' => 'error', 'message' => 'Missing required fields'), admin_url('options-general.php')));
            exit;
        }

        if ($this->installer) {
            $result = $this->installer->install($repo, $branch, $slug);
            if (is_wp_error($result)) {
                $message = $result->get_error_message();
                wp_redirect(add_query_arg(array('page' => 'git-updater', 'install_status' => 'error', 'message' => urlencode($message)), admin_url('options-general.php')));
                exit;
            }
        } else {
            wp_redirect(add_query_arg(array('page' => 'git-updater', 'install_status' => 'error', 'message' => 'Installer not available'), admin_url('options-general.php')));
            exit;
        }

        wp_redirect(add_query_arg(array('page' => 'git-updater', 'install_status' => 'success'), admin_url('options-general.php')));
        exit;
    }

    public function render_token_field()
    {
        $token = get_option('git_updater_token');
        ?>
        <input type="password" name="git_updater_token" value="<?php echo esc_attr($token); ?>" class="regular-text" />
        <p class="description">Required for private repositories and higher API rate limits.</p>
        <?php
    }

    public function render_repos_field()
    {
        $repos = get_option('git_updater_repos', array());
        if (!is_array($repos)) {
            $repos = array();
        }

        ?>
        <div id="git-updater-repos-wrapper">
            <p class="description">Map plugin directory names to GitHub repositories.</p>
            <?php foreach ($repos as $repo): ?>
                <div class="repo-row" style="margin-bottom: 10px;">
                    <input type="text" name="git_updater_repos[][plugin]" placeholder="Plugin Directory Name"
                        value="<?php echo esc_attr($repo['plugin']); ?>" class="regular-text" style="width: 250px;" />
                    <input type="text" name="git_updater_repos[][repo]" placeholder="owner/repo"
                        value="<?php echo esc_attr($repo['repo']); ?>" class="regular-text" style="width: 250px;" />
                    <input type="text" name="git_updater_repos[][branch]" placeholder="Branch (default: main)"
                        value="<?php echo isset($repo['branch']) ? esc_attr($repo['branch']) : ''; ?>" class="regular-text"
                        style="width: 150px;" />
                    <button class="button git-updater-remove-repo">Remove</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button class="button" id="git-updater-add-repo">Add Repository</button>
        <?php
    }
}
