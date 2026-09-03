<?php
/**
 * Plugin Name:       JetBackup
 * Plugin URI:        https://www.jetbackup.com/jetbackup-for-wordpress
 * Description:       JetBackup is the most complete WordPress site backup and restore plugin. We offer the easiest way to backup, restore or migrate your site. You can backup your files, database or both.
 * Version:           3.1.23.6
 * Author:            JetBackup
 * Author URI:        https://www.jetbackup.com/jetbackup-for-wordpress
 * License:           GPLv2 or later
 */

if (!defined('WPINC')) die('Direct access is not allowed');

if (!defined('__JETBACKUP__')) define('__JETBACKUP__', true);
if (!defined('JB_ROOT')) define('JB_ROOT', dirname(__FILE__));
if (!defined('WP_ROOT')) define('WP_ROOT', rtrim(dirname(WP_CONTENT_DIR), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);

require_once JB_ROOT . '/src/JetBackup/autoload.php';

// Installation procedures
register_activation_hook(__FILE__, ['\JetBackup\Wordpress\Installer', 'install']);
register_uninstall_hook(__FILE__,  ['\JetBackup\Wordpress\Installer', 'uninstall']);
register_deactivation_hook(__FILE__, ['\JetBackup\Wordpress\Installer', 'deactivate']);

// Main init
add_action('init', ['\JetBackup\Wordpress\Init', 'actionInit'], 1);
add_action('init', ['\JetBackup\Wordpress\Init', 'actionCLI'], 1);

// WordPress Abilities API (WP 6.9+)
add_action('wp_abilities_api_categories_init', ['\JetBackup\Wordpress\Abilities', 'registerCategories']);
add_action('wp_abilities_api_init', ['\JetBackup\Wordpress\Abilities', 'register']);

add_action('upgrader_process_complete', ['\JetBackup\Wordpress\Installer', 'update'], 10, 2);
add_filter('admin_body_class', ['\JetBackup\Wordpress\Init', 'filterAdminBodyClass']);

add_action('admin_bar_menu', function ($wp_admin_bar) {
	return \JetBackup\Wordpress\Init::guard(
		['\JetBackup\Wordpress\UI', 'addTopMenuBarIntegration'],
		[$wp_admin_bar],
		null
	);
}, 100);

add_filter('plugin_action_links_backup/backup.php', function ($links) {
	return \JetBackup\Wordpress\Init::guard(
		['\JetBackup\Wordpress\UI', 'addActionLinks'],
		[$links],
		$links
	);
});

add_filter('plugin_row_meta', function ($links, $file) {
	return \JetBackup\Wordpress\Init::guard(
		['\JetBackup\Wordpress\UI', 'addRowMeta'],
		[$links, $file],
		$links
	);
}, 10, 2);

add_filter('site_transient_update_plugins', function ($transient) {
	try {
		if (!class_exists('\JetBackup\Wordpress\Update')) return $transient;
		$result = \JetBackup\Wordpress\Update::check($transient);
		return ($result instanceof stdClass) ? $result : $transient;
	} catch (\Throwable $e) {
		error_log('[JetBackup] Update::check failed: ' . $e->getMessage());
		return $transient;
	}
});
