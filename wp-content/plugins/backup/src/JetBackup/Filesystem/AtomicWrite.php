<?php

namespace JetBackup\Filesystem;

use Exception;
use JetBackup\Log\LogController;

if (!defined('__JETBACKUP__')) die('Direct access is not allowed');

class AtomicWrite {

	/** @var int Number of write attempts */
	const MAX_RETRIES = 3;

	/** @var int Delay between retries in milliseconds */
	const RETRY_DELAY_MS = 200; // 0.2s

	/**
	 * Main atomic write method.
	 *
	 * @param string $path    Target file path
	 * @param string $content File content to write
	 * @param LogController|null $logger Optional logger for error messages
	 *
	 * @throws Exception on failure
	 * @return bool
	 */
	public static function write(string $path, string $content, ?LogController $logger = null): bool {
		$dir       = dirname($path);
		$swapFile  = $path . '.swap';

		// Ensure directory exists (best-effort)
		if (!is_dir($dir)) {
			@mkdir($dir, 0700, true);
			if (!is_dir($dir)) throw new Exception("AtomicWrite error: Cannot create directory: {$dir}");
		}

		for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {

			$phpError = null;
			set_error_handler(function ($severity, $message) use (&$phpError) {
				$phpError = $message;
				return true;
			});

			$bytes = @file_put_contents($swapFile, $content, LOCK_EX);
			restore_error_handler();

			if ($bytes === false) {
				$msg = "AtomicWrite: Failed writing swap file {$swapFile}" . ($phpError ? " — OS Error: {$phpError}" : "");
				self::logError($msg, $logger);
				if ($attempt === self::MAX_RETRIES) throw new Exception($msg);
				usleep(self::RETRY_DELAY_MS * 1000);
				continue;
			}

			$phpError = null;
			set_error_handler(function ($severity, $message) use (&$phpError) {
				$phpError = $message;
				return true;
			});

			$renamed = @rename($swapFile, $path);
			restore_error_handler();

			if ($renamed === true) return true; // Success

			// Special case: swap disappeared / file exists / treat as success
			if (!file_exists($swapFile) && file_exists($path)) return true;

			$msg = "AtomicWrite: Failed renaming {$swapFile} → {$path}" . ($phpError ? " — OS Error: {$phpError}" : "");
			self::logError($msg, $logger);
			if ($attempt === self::MAX_RETRIES) throw new Exception($msg);

			@unlink($swapFile);
			usleep(self::RETRY_DELAY_MS * 1000);
		}

		throw new Exception("AtomicWrite: Unknown error writing {$path}");
	}

	/**
	 * Internal logging helper.
	 */
	private static function logError(string $msg, ?LogController $logger): void  {
		if ($logger instanceof LogController) $logger->logError($msg);
	}

}
