<?php

namespace JetBackup\Archive;

use JetBackup\DirIterator\DirIteratorFile;
use JetBackup\Exception\GzipException;

if (!defined( '__JETBACKUP__')) die('Direct access is not allowed');

/**
 * Class for compressing files using gzip.
 */
class Gzip {

	const MAX_RETRIES = 3;
	const RETRY_DELAY_MS = 100;
	const DEFAULT_COMPRESS_CHUNK_SIZE = 10485760; // 10MB
	const DEFAULT_DECOMPRESS_CHUNK_SIZE = 1048576; // 1MB
	const DEFAULT_COMPRESSION_LEVEL = -1;

	private function __construct() {}

	/**
	 * @throws GzipException
	 */
	private static function _getInfo($file) {
		$info = new \stdClass();
		if(file_exists($file)) {
			$info = json_decode(file_get_contents($file));
			if($info === false) throw new GzipException("Failed fetching compress information");
		}
		return $info;
	}

	/**
	 * @param $file
	 * @param $data
	 *
	 * @return void
	 * @throws GzipException
	 */
	private static function _putInfo($file, $data)
	{
		$tempFile = $file . '.tmp';

		$jsonData = json_encode($data);
		if ($jsonData === false) throw new GzipException("Failed to encode compress information: " . json_last_error_msg());

		$maxRetries     = self::MAX_RETRIES;
		$retryDelayMs   = self::RETRY_DELAY_MS;
		$lastErrorWrite = null;
		$bytesWritten = false;

		for ($i = 0; $i < $maxRetries; $i++) {

			$lastErrorWrite = null;

			set_error_handler(function ($severity, $message, $errFile, $errLine) use (&$lastErrorWrite) {
				$lastErrorWrite = $message . " in {$errFile}:{$errLine}";
				return true;
			});

			$bytesWritten = @file_put_contents($tempFile, $jsonData);
			restore_error_handler();

			if ($bytesWritten !== false) break;
			usleep($retryDelayMs * 1000); // retry
		}

		if ($bytesWritten === false) {
			$extra = $lastErrorWrite ? " ({$lastErrorWrite})" : '';
			throw new GzipException("Failed to write compress information to temporary file '{$tempFile}' after {$maxRetries} attempts{$extra}");
		}

		$lastErrorRename = null;

		for ($i = 0; $i < $maxRetries; $i++) {

			$lastErrorRename = null;

			set_error_handler(function ($severity, $message, $errFile, $errLine) use (&$lastErrorRename) {
				$lastErrorRename = $message . " in {$errFile}:{$errLine}";
				return true;
			});

			$renamed = @rename($tempFile, $file);
			restore_error_handler();

			if ($renamed === true) return;
			usleep($retryDelayMs * 1000);
		}

		$extra = $lastErrorRename ? " ({$lastErrorRename})" : '';
		throw new GzipException("Failed to atomically write compress information to '{$file}' after {$maxRetries} attempts{$extra}");
	}



	/**
	 * @throws GzipException
	 */
	public static function compress($file, $chunkSize=self::DEFAULT_COMPRESS_CHUNK_SIZE, $compressionLevel=self::DEFAULT_COMPRESSION_LEVEL, ?callable $callback=null) {
		if(!file_exists($file) || !is_file($file)) throw new GzipException("Source file not found");

		$target = $file . '.gz';

		$info_file = $target . '.compress.info';
		$info = self::_getInfo($info_file);

		$fd = fopen($file, 'r');
		$gzfd = fopen($target, 'ab');

		$read = $write = 0;
		if(isset($info->fdpos) && $info->fdpos) {
			$read = $info->fdpos;
			fseek($fd, $info->fdpos);
		}
		if(isset($info->fdgzpos) && $info->fdgzpos) {
			$write = $info->fdgzpos;
			fseek($gzfd, $info->fdgzpos);
		}

		$fileSize = DirIteratorFile::safe_filesize($file);

		while(!feof($fd)) {
			// Call callback BEFORE the expensive gzencode operation to check execution time
			// This allows graceful exit before starting work that might exceed time limits
			if($callback) $callback($read, $fileSize);

			$chunk = fread($fd, $chunkSize);
			$bytesRead = strlen($chunk);

			if($bytesRead === 0) break;

			$compressed = gzencode($chunk, $compressionLevel);
			$bytesWritten = fwrite($gzfd, $compressed);

			$read += $bytesRead;
			$write += $bytesWritten;

			self::_putInfo($info_file, [
				'fdpos'         => $read,
				'fdgzpos'       => $write,
			]);
		}

		if(feof($fd)) {
			@unlink($file);
			@unlink($info_file);
		}

		fclose($fd);
		fclose($gzfd);
	}

	/**
	 * @throws GzipException
	 */
	public static function decompress($file, ?callable $callback=null, $chunkSize=self::DEFAULT_DECOMPRESS_CHUNK_SIZE) {
		if(!file_exists($file) || !is_file($file)) throw new GzipException("Source file not found");

		$info_file = $file . '.decompress.info';
		$info = self::_getInfo($info_file);

		// remove .gz suffix
		$target = substr($file, 0, -3);

		$fd = fopen($target, 'a');
		$gzfd = gzopen($file, 'rb');

		$read = $write = 0;
		if(isset($info->fdpos) && $info->fdpos) {
			$write = $info->fdpos;
			fseek($fd, $info->fdpos);
		}

		if(isset($info->fdgzpos) && $info->fdgzpos) {
			$read = $info->fdgzpos;
			gzseek($gzfd, $info->fdgzpos);
		}

		$estimatedTotalSize = (int) (DirIteratorFile::safe_filesize($file) * 3); // estimating X3 compression ratio

		while(!gzeof($gzfd)) {
			// Call callback BEFORE the expensive gzread operation to check execution time
			if($callback) $callback('Gzip', 'Decompressing', $estimatedTotalSize, $read);

			$chunk = gzread($gzfd, $chunkSize);
			$bytesRead = strlen($chunk);

			if($bytesRead === 0) break;

			$bytesWritten = fwrite($fd, $chunk);

			$read += $bytesRead;
			$write += $bytesWritten;

			self::_putInfo($info_file, [
				'fdpos'         => $write,
				'fdgzpos'       => $read,
			]);
		}

		if(gzeof($gzfd)) {
			@unlink($file);
			@unlink($info_file);
		}

		fclose($fd);
		gzclose($gzfd);
	}
}