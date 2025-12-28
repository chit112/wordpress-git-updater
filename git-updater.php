<?php
/**
 * Plugin Name: Git Updater
 * Plugin URI: https://example.com/git-updater
 * Description: Keeps plugins up to date from GitHub repositories (public and private).
 * Version: 1.0.0
 * Author: Antigravity
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GIT_UPDATER_VERSION', '1.0.0' );
define( 'GIT_UPDATER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GIT_UPDATER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once GIT_UPDATER_PLUGIN_DIR . 'includes/class-git-updater-settings.php';
require_once GIT_UPDATER_PLUGIN_DIR . 'includes/class-git-updater-api.php';
require_once GIT_UPDATER_PLUGIN_DIR . 'includes/class-git-updater-upgrader.php';

class Git_Updater {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public function init() {
		$settings = new Git_Updater_Settings();
		$api      = new Git_Updater_API();
		$upgrader = new Git_Updater_Upgrader( $settings, $api );
	}
}

Git_Updater::get_instance();
