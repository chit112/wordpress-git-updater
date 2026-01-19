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
        add_action('admin_post_git_updater_save_repos', array($this, 'handle_save_repos'));
        add_action('admin_post_git_updater_force_reinstall', array($this, 'handle_force_reinstall'));
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
            array($this, 'create_admin_page') // Updated to call the new method name
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

    public function create_admin_page() // Renamed from display_settings_page
    {
        $this->options = get_option('git_updater_token');
        ?>
        <div class="wrap">
            <h1>🚀 Git Updater <small style="font-size: 0.5em;">v<?php echo esc_html(GIT_UPDATER_VERSION); ?></small></h1>
            <p>
                Manage your private GitHub plugins with ease.
                <a href="https://github.com/chit112/wordpress-git-updater" target="_blank">📖 Documentation</a> |
                <a href="https://github.com/chit112/wordpress-git-updater/issues" target="_blank">🐛 Report Issue</a>
            </p>
            <hr>

            <form method="post" action="options.php">
                <?php
                settings_fields('git_updater_options');
                ?>

                <!-- Section 1: Authentication -->
                <div class="card" style="max-width: 100%; padding: 20px; margin-top: 20px;">
                    <h2>🔑 1. GitHub Authentication</h2>
                    <p class="description">
                        You need a Personal Access Token (PAT) to access private repositories.
                        <a href="https://github.com/settings/tokens" target="_blank">Generate one here</a> (select
                        <code>repo</code> scope).
                    </p>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">GitHub Access Token</th>
                            <td>
                                <input type="password" name="git_updater_token[token]"
                                    value="<?php echo isset($this->options['token']) ? esc_attr($this->options['token']) : ''; ?>"
                                    class="regular-text" />
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save Token'); ?>
                </div>
            </form>

            <hr>

            <!-- Section 2: Install New Plugin -->
            <div class="card" style="max-width: 100%; padding: 20px; margin-top: 20px;">
                <h2>📦 2. Install New Plugin</h2>
                <p class="description">Directly install a plugin from a GitHub URL or short slug. It will automatically be added
                    to your monitored list below.</p>

                <?php
                if (isset($_GET['message'])) {
                    $status_class = (isset($_GET['install_status']) && $_GET['install_status'] === 'error') ? 'notice-error' : 'notice-success';
                    echo '<div class="notice ' . $status_class . ' is-dismissible"><p>' . esc_html(urldecode($_GET['message'])) . '</p></div>';
                }
                ?>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="git_updater_install">
                    <?php wp_nonce_field('git_updater_install', 'git_updater_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="install_repo">Repository</label></th>
                            <td>
                                <input name="repo" type="text" id="install_repo" value="" class="regular-text"
                                    placeholder="https://github.com/owner/repo" required>
                                <p class="description">Full URL or <code>owner/repo</code>.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="install_branch">Branch</label></th>
                            <td>
                                <input name="branch" type="text" id="install_branch" value="" class="regular-text"
                                    placeholder="main">
                                <p class="description">Leave empty for <code>main</code>.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="install_target">Target Slug</label></th>
                            <td>
                                <input name="target_slug" type="text" id="install_target" value="" class="regular-text"
                                    placeholder="my-plugin-folder" required>
                                <p class="description">The folder name for the plugin.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Install Plugin', 'primary', 'btn_install_plugin'); ?>
                </form>
            </div>

            <hr>

            <!-- Section 3: Monitored Repositories -->
            <div class="card" style="max-width: 100%; padding: 20px; margin-top: 20px;">
                <h2>📝 3. Installed & Monitored Plugins</h2>
                <p class="description">These plugins are currently being monitored. Use the "Update Plugin" button to fetch the
                    latest code from GitHub.</p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="git_updater_save_repos">
                    <?php wp_nonce_field('git_updater_save_repos', 'git_updater_repos_nonce'); ?>
                    <table class="widefat fixed" cellspacing="0">
                        <thead>
                            <tr>
                                <th><strong>Plugin Slug</strong> <span class="description">(Folder name)</span></th>
                                <th><strong>Repository</strong> <span class="description">(owner/repo)</span></th>
                                <th><strong>Branch</strong> <span class="description">(Default: main)</span></th>
                                <th><strong>Actions</strong></th>
                            </tr>
                        </thead>
                        <tbody id="git-updater-repos-list">
                            <?php
                            $repos = get_option('git_updater_repos', array());
                            if (!empty($repos)) {
                                foreach ($repos as $index => $repo) {
                                    $this->render_repo_row($index, $repo);
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                    <?php
                    // Sync SHAs URL removed
                    ?>
                    <p>
                        <!-- Manual add removed for safety -->
                        <input type="submit" name="btn_save_repos" class="button button-primary" value="Save Changes" />
                    </p>
                </form>
            </div>

            <hr>

            <!-- Troubleshooting -->
            <div class="card" style="max-width: 100%; padding: 20px; margin-top: 20px; border-left: 4px solid #ffba00;">
                <details>
                    <summary style="cursor: pointer; font-weight: bold; font-size: 1.1em;">📝 Troubleshooting & Logs (Click to
                        expand)</summary>
                    <div style="margin-top: 15px;">
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
                            <textarea class="large-text code" rows="15"
                                readonly><?php echo esc_textarea($log_content); ?></textarea>
                            <p>
                                <input type="submit" name="btn_clear_logs" class="button" value="Clear Logs" />
                            </p>
                        </form>
                    </div>
                </details>
            </div>
        </div>
        <?php
    }

    public function handle_install()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('git_updater_install', 'git_updater_nonce');

        $repo = sanitize_text_field($_POST['repo']);

        // Clean up repo input if user pasted full URL
        $repo = preg_replace('/^(?:https?:\/\/)?(?:www\.)?github\.com\//', '', $repo);
        $repo = preg_replace('/\.git$/', '', $repo);
        $repo = trim($repo, '/');

        $branch = sanitize_text_field($_POST['branch']);
        $slug = sanitize_text_field($_POST['target_slug']);

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

    public function handle_force_reinstall()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('git_updater_force_reinstall');

        $repo = sanitize_text_field($_GET['repo']);
        $branch = sanitize_text_field($_GET['branch']);
        $slug = sanitize_text_field($_GET['slug']);

        if (empty($repo) || empty($slug)) {
            wp_redirect(add_query_arg(array('page' => 'git-updater', 'install_status' => 'error', 'message' => 'Missing required fields for reinstall'), admin_url('options-general.php')));
            exit;
        }

        Git_Updater_Logger::log("Force update triggered for $slug ($repo).");

        if ($this->installer) {
            // Pass true for overwrite
            $result = $this->installer->install($repo, $branch, $slug, true);
            if (is_wp_error($result)) {
                $message = $result->get_error_message();
                wp_redirect(add_query_arg(array('page' => 'git-updater', 'install_status' => 'error', 'message' => urlencode($message)), admin_url('options-general.php')));
                exit;
            }
        } else {
            wp_redirect(add_query_arg(array('page' => 'git-updater', 'install_status' => 'error', 'message' => 'Installer not available'), admin_url('options-general.php')));
            exit;
        }

        wp_redirect(add_query_arg(array('page' => 'git-updater', 'install_status' => 'success', 'message' => 'Plugin updated successfully.'), admin_url('options-general.php')));
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
    public function handle_save_repos()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('git_updater_save_repos', 'git_updater_repos_nonce');

        if (isset($_POST['git_updater_repos']) && is_array($_POST['git_updater_repos'])) {
            $cleaned_repos = array();
            $seen_repos = array(); // For deduplication

            foreach ($_POST['git_updater_repos'] as $repo) {
                if (!empty($repo['plugin']) && !empty($repo['repo'])) {
                    $plugin = sanitize_text_field($repo['plugin']);
                    $repo_slug = sanitize_text_field($repo['repo']);
                    $branch = sanitize_text_field($repo['branch']);

                    // Create a unique key for deduplication
                    $key = $plugin . '|' . $repo_slug;

                    if (!isset($seen_repos[$key])) {
                        $cleaned_repos[] = array(
                            'plugin' => $plugin,
                            'repo' => $repo_slug,
                            'branch' => $branch
                        );
                        $seen_repos[$key] = true;
                    }
                }
            }
            update_option('git_updater_repos', $cleaned_repos);
        } else {
            update_option('git_updater_repos', array());
        }

        wp_redirect(add_query_arg('page', 'git-updater', admin_url('options-general.php')));
        exit;
    }

    public function render_repo_row($index, $repo)
    {
        $force_reinstall_url = wp_nonce_url(
            admin_url('admin-post.php?action=git_updater_force_reinstall&repo=' . urlencode($repo['repo']) . '&branch=' . urlencode(isset($repo['branch']) ? $repo['branch'] : 'main') . '&slug=' . urlencode($repo['plugin'])),
            'git_updater_force_reinstall'
        );
        ?>
        <tr>
            <td>
                <code><?php echo esc_html($repo['plugin']); ?></code>
                <input type="hidden" name="git_updater_repos[<?php echo $index; ?>][plugin]"
                    value="<?php echo esc_attr($repo['plugin']); ?>" />
            </td>
            <td>
                <a href="https://github.com/<?php echo esc_attr($repo['repo']); ?>"
                    target="_blank"><?php echo esc_html($repo['repo']); ?></a>
                <input type="hidden" name="git_updater_repos[<?php echo $index; ?>][repo]"
                    value="<?php echo esc_attr($repo['repo']); ?>" />
            </td>
            <td>
                <code><?php echo isset($repo['branch']) ? esc_html($repo['branch']) : 'main'; ?></code>
                <input type="hidden" name="git_updater_repos[<?php echo $index; ?>][branch]"
                    value="<?php echo isset($repo['branch']) ? esc_attr($repo['branch']) : 'main'; ?>" />
            </td>
            <td>
                <a href="<?php echo $force_reinstall_url; ?>" class="button"
                    onclick="return confirm('This will reinstall the plugin from GitHub. Continue?');"
                    title="Reinstall plugin from latest GitHub code">Update Plugin</a>
                <button type="button" class="button git-updater-remove-repo">Remove</button>
            </td>
        </tr>
        <?php
    }
}
