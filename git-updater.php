<?php
/**
 * Plugin Name:       Git Updater
 * Plugin URI:        https://github.com/chit112/wordpress-git-updater
 * Description:       A plugin to update other WordPress plugins directly from GitHub repositories (private or public).
 * Version:           1.0.3
 * Author:            Rune Brimer
 * Author URI:        https://github.com/chit112
 * License:           GPL v2 or later
 * Text Domain:       git-updater
 */

if (!defined('ABSPATH')) {
	exit;
}

define('GIT_UPDATER_VERSION', '1.0.3');
define('GIT_UPDATER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GIT_UPDATER_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once GIT_UPDATER_PLUGIN_DIR . 'includes/class-git-updater-settings.php';
require_once GIT_UPDATER_PLUGIN_DIR . 'includes/class-git-updater-logger.php';
require_once GIT_UPDATER_PLUGIN_DIR . 'includes/class-git-updater-api.php';
require_once GIT_UPDATER_PLUGIN_DIR . 'includes/class-git-updater-installer.php';

class Git_Updater
{

	private static $instance = null;

	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct()
	{
		add_action('plugins_loaded', array($this, 'init'));
	}

	public function init()
	{
		$settings = new Git_Updater_Settings();
		$api = new Git_Updater_API();
		$installer = new Git_Updater_Installer($api);
		$settings->set_installer($installer);
	}
}

Git_Updater::get_instance();
