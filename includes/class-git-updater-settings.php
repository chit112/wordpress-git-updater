<?php

if (!defined('ABSPATH')) {
    exit;
}

class Git_Updater_Settings
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
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
            <form method="post" action="options.php">
                <?php
                settings_fields('git_updater_options');
                do_settings_sections('git-updater');
                submit_button();
                ?>
            </form>
        </div>
        <?php
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
        // Placeholder for repeater field
        $repos = get_option('git_updater_repos', array());
        if (!is_array($repos)) {
            $repos = array();
        }

        ?>
        <div id="git-updater-repos-wrapper">
            <p class="description">Map plugin directory names to GitHub repositories (e.g., <code>my-plugin</code> ->
                <code>owner/repo</code>).
            </p>
            <?php if (empty($repos)): ?>
                <div class="repo-row">
                    <input type="text" name="git_updater_repos[0][plugin]" placeholder="Plugin Directory Name" value="" />
                    <input type="text" name="git_updater_repos[0][repo]" placeholder="owner/repo" value="" />
                </div>
            <?php else: ?>
                <?php foreach ($repos as $index => $repo): ?>
                    <div class="repo-row">
                        <input type="text" name="git_updater_repos[<?php echo $index; ?>][plugin]" placeholder="Plugin Directory Name"
                            value="<?php echo esc_attr($repo['plugin']); ?>" />
                        <input type="text" name="git_updater_repos[<?php echo $index; ?>][repo]" placeholder="owner/repo"
                            value="<?php echo esc_attr($repo['repo']); ?>" />
                        <input type="text" name="git_updater_repos[<?php echo $index; ?>][branch]" placeholder="Branch (default: main)"
                            value="<?php echo isset($repo['branch']) ? esc_attr($repo['branch']) : ''; ?>" />
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <!-- TODO: Add JavaScript to add more rows dynamically if needed, 
                 for now we just rely on saving and maybe adding an empty row if the last one is filled, 
                 or a dedicated "Add" button handling in a real JS file. 
                 For MVP, let's keep it simple or allow entering JSON/TextArea for easier bulk edit if this UI gets complex.
                 Let's stick to a Text Area for JSON or line-separated list for simplicity in v1 if JS is too much, 
                 but a simple row is better. 
            -->
            <p><em>Save to add more. (Basic implementation)</em></p>
            <div class="repo-row">
                <input type="text" name="git_updater_repos[<?php echo count($repos); ?>][plugin]"
                    placeholder="New Plugin Directory Name" value="" />
                <input type="text" name="git_updater_repos[<?php echo count($repos); ?>][repo]" placeholder="owner/repo"
                    value="" />
                <input type="text" name="git_updater_repos[<?php echo count($repos); ?>][branch]"
                    placeholder="Branch (default: main)" value="" />
            </div>
        </div>
        <?php
    }
}
