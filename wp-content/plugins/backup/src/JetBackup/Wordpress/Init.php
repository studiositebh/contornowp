<?php

namespace JetBackup\Wordpress;

use JetBackup\BackupJob\BackupJob;
use JetBackup\CLI\CLI;
use JetBackup\Destination\Destination;
use JetBackup\Download\Download;
use JetBackup\Downloader\Downloader;
use JetBackup\Entities\Util;
use JetBackup\Exception\JBException;
use JetBackup\Factory;
use JetBackup\JetBackup;
use JetBackup\Queue\QueueItem;
use JetBackup\Schedule\Schedule;
use JetBackup\SGB\Migration;
use JetBackup\UserInput\UserInput;

if (!defined( '__JETBACKUP__')) die('Direct access is not allowed');

class Init {

	private static bool $_initialized = false;

	private function __construct() { } // only static method

	public static function isInitialized(): bool {
		return self::$_initialized;
	}

	/**
	 * Detect whether the site is likely running on WP Cloud / Atomic (e.g., Porkbun WP Cloud).
	 *
	 * Logic:
	 * 1) IS_ATOMIC must exist and evaluate to true
	 * 2) AND at least one of ATOMIC_CLIENT_ID / ATOMIC_SITE_ID must exist (to reduce false positives)
	 */
	public static function isWpCloudAtomic(): bool
	{
		// Fast fail: constant not present at all
		if (!\defined('IS_ATOMIC')) {
			return false;
		}

		// Some platforms may define it as bool/int/string; normalize safely
		$isAtomic = \constant('IS_ATOMIC');

		// Treat 1 / "1" / true / "true" as true
		$isAtomic = ($isAtomic === true)
		            || ($isAtomic === 1)
		            || ($isAtomic === '1')
		            || (\is_string($isAtomic) && \strcasecmp($isAtomic, 'true') === 0);

		if (!$isAtomic) {
			return false;
		}

		// Extra signals to avoid collisions with other platforms that might define IS_ATOMIC
		$hasClientId = \defined('ATOMIC_CLIENT_ID') && \constant('ATOMIC_CLIENT_ID') !== null && \constant('ATOMIC_CLIENT_ID') !== '';
		$hasSiteId   = \defined('ATOMIC_SITE_ID')   && \constant('ATOMIC_SITE_ID')   !== null && \constant('ATOMIC_SITE_ID')   !== '';

		return ($hasClientId || $hasSiteId);
	}

	/**
	 * Run a callable only if JetBackup init completed successfully.
	 * Never throws (logs and returns default).
	 *
	 * @param callable $callable
	 * @param array $args
	 * @param mixed $default
	 * @return mixed
	 */
	public static function guard(callable $callable, array $args = [], $default = null) {
		try {
			if (!self::isInitialized()) return $default;
			return \call_user_func_array($callable, $args);
		} catch (\Throwable $e) {
			error_log('[JetBackup] guarded hook failed: ' . $e->getMessage());
			return $default;
		}
	}

	/**
	 * @return void
	 */
	public static function actionInit() {
		if (!function_exists('current_user_can') || !current_user_can('manage_options')) return;

		try {

			load_plugin_textdomain('jetbackup', false, JetBackup::PLUGIN_NAME . DIRECTORY_SEPARATOR . 'languages');

			// Cookie Env (safe)
			add_action('wp_loaded', ['\JetBackup\Wordpress\Wordpress', 'setNonceCookie']);
			add_action('wp_loaded', ['\JetBackup\Wordpress\Wordpress', 'setUserLanguageCookie']);

			self::_createWorkingSpace();
			self::_validateWorkingSpace();

			(new Migration())->migrate();
			Destination::createDefaultDestination();
			Schedule::createDefaultSchedule();

			BackupJob::getDefaultJob();
			BackupJob::getDefaultConfigJob();

			// On multisite, only a Super Admin on the main site may proceed. This gate MUST run
			// BEFORE _download() reads request input; otherwise a per-site administrator (who has
			// manage_options) could stream the whole-network backup or job logs via the
			// download_id / queue_item_id request parameters.
			$hookNetworkMenu = false;
			if (Helper::isMultisite()) {

				if (!Helper::isMainSite() || !Helper::isNetworkAdminUser()) return;
				$hookNetworkMenu = Helper::isNetworkAdminInterface();
			}

			self::_download();

			//Only register UI/AJAX/heartbeat after we know init succeeded.
			if ($hookNetworkMenu) add_action('network_admin_menu', ['\JetBackup\Wordpress\UI', 'main']);

			self::$_initialized = true;

			add_action('admin_menu', ['\JetBackup\Wordpress\UI', 'main']);
			add_action('wp_ajax_jetbackup_api', ['\JetBackup\Ajax\Ajax', 'main']);

			if (Factory::getSettingsAutomation()->isHeartbeatEnabled()) {
				add_action('admin_footer', ['\JetBackup\Wordpress\UI', 'heartbeat']);
				add_action('wp_ajax_jetbackup_heartbeat', ['\JetBackup\Ajax\Ajax', 'heartbeat']);
			}

			if (self::isWpCloudAtomic()) {

				$restore = Factory::getSettingsRestore();
				$changed = false;

				if (!$restore->isRestoreWpContentOnlyEnabled()) {
					$restore->setRestoreWpContentOnly(true);
					$changed = true;
				}

				if (!$restore->isRestoreAlternatePathEnabled()) {
					$restore->setRestoreAlternatePath(true);
					$changed = true;
				}

				if ($changed) $restore->save();

			}


		} catch (\Throwable $e) {

			error_log('[JetBackup] actionInit failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

			// if any hooks were added earlier, remove them
			remove_action('admin_menu', ['\JetBackup\Wordpress\UI', 'main']);
			remove_action('network_admin_menu', ['\JetBackup\Wordpress\UI', 'main']);
			remove_action('admin_footer', ['\JetBackup\Wordpress\UI', 'heartbeat']);
			remove_action('wp_ajax_jetbackup_api', ['\JetBackup\Ajax\Ajax', 'main']);
			remove_action('wp_ajax_jetbackup_heartbeat', ['\JetBackup\Ajax\Ajax', 'heartbeat']);

			if (function_exists('is_admin') && is_admin()) {
				add_action('admin_notices', function () use ($e) {
					if (!current_user_can('manage_options')) return;
					echo '<div class="notice notice-error"><p><strong>JetBackup:</strong> '
					     . esc_html('JetBackup failed to initialize and was disabled for this request. Reason: ' . $e->getMessage())
					     . '</p></div>';
				});
			}

			return;
		}
	}



	private static function _download():void {
		try {
			$userInput = new UserInput();
			$userInput->setData($_REQUEST);

			if($download_id = $userInput->getValidated('download_id', 0, UserInput::UINT)) {
				$download = new Download($download_id);
				if(!$download->getId()) throw new JBException('The provided download id not found');
				$download->download();
			}

			if($queue_item_id = $userInput->getValidated('queue_item_id', 0, UserInput::UINT)) {
				$queue_item = new QueueItem($queue_item_id);
				if(!$queue_item->getId()) throw new JBException('The provided queue item id not found');
				$downloader = new Downloader($queue_item->getLogFile());
				$downloader->download();
			}
		} catch(JBException $e) {
			wp_die(esc_html('JetBackup: ' . $e->getMessage()));
		}
	}

	private static function _getWorkingSpaceLockFile(): string
	{
		return Factory::getLocations()->getDataDir()
		       . JetBackup::SEP
		       . Factory::getConfig()->getUniqueID()
		       . '.lock';
	}

	private static function _getWorkingSpaceLockFileValue(): string
	{
		$lockFile = self::_getWorkingSpaceLockFile();
		if (!file_exists($lockFile)) return '';
		$content = @file_get_contents($lockFile);
		if ($content === false) return ''; // Treat as corrupted; let validator handle it
		return trim($content);
	}

	private static function _getInstallFingerprint(): string
	{
		global $wpdb;

		$dbName  = $wpdb->dbname ?? '';
		$prefix  = $wpdb->prefix ?? '';

		$secret  = defined('AUTH_KEY')
			? AUTH_KEY
			: Factory::getConfig()->getEncryptionKey();

		return sha1($dbName . '|' . $prefix . '|' . $secret);
	}

	private static function _updateWorkingSpaceLockFile(): void
	{
		$lockFile     = self::_getWorkingSpaceLockFile();
		$fingerprint  = self::_getInstallFingerprint();

		if (file_put_contents($lockFile, $fingerprint, LOCK_EX) !== false) {
			@chmod($lockFile, 0400);
		}
	}

	private static function _validateWorkingSpace(): void
	{
		// Only lock the folder if we are using an alternate datadir
		// Regular setup is inside wp-content which is per-install
		if (empty(Factory::getConfig()->getAlternateDataFolder())) return;

		$lockFile = self::_getWorkingSpaceLockFile();

		// First run: create lock file and exit
		if (!file_exists($lockFile)) {
			self::_updateWorkingSpaceLockFile();
			return;
		}

		$storedFingerprint  = self::_getWorkingSpaceLockFileValue();
		$currentFingerprint = self::_getInstallFingerprint();

		if (!hash_equals($storedFingerprint, $currentFingerprint)) {
			error_log('JetBackup: Alternate data folder reset due to mismatched installation fingerprint.');
			Factory::getConfig()->setAlternateDataFolder('');
			Factory::getConfig()->save();
		}
	}


	private static function _createWorkingSpace() {

		$folders = [
			Factory::getLocations()->getDataDir(),
			Factory::getLocations()->getTempDir(),
			Factory::getLocations()->getDatabaseDir(),
			Factory::getLocations()->getDownloadsDir(),
			Factory::getLocations()->getBackupsDir(),
			Factory::getLocations()->getLogsDir(),
		];

		foreach ($folders as $folder) Util::secureFolder($folder);

	}

	public static function filterAdminBodyClass($classes) {
		$screen = Helper::getCurrentScreen();
		if ($screen && strpos($screen, 'jetbackup') !== false) $classes .= ' jetbackup';
		return $classes;
	}

	public static function actionCLI() {
		if (defined('WP_CLI') && WP_CLI) CLI::init();
	}

}
