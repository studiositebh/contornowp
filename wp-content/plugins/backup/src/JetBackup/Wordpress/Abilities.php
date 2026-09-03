<?php

namespace JetBackup\Wordpress;

use JetBackup\Ajax\iAjax;
use JetBackup\Exception\JBException;
use JetBackup\Factory;
use JetBackup\JetBackup;

if (!defined( '__JETBACKUP__')) die('Direct access is not allowed');

class Abilities {

	const CAPABILITY = 'manage_options';

	const CATEGORY = 'jetbackup';
	const CATEGORY_LABEL = 'JetBackup';
	const CATEGORY_DESCRIPTION = 'JetBackup backup, restore and status capabilities.';

	const INPUT_NONE = 0;
	const INPUT_PAGINATION = 1;
	const INPUT_ID = 2;

	const KEY_SKIP = 'skip';
	const KEY_LIMIT = 'limit';
	const LIMIT_DEFAULT = 50;
	const LIMIT_MAX = 100;

	const ARG_LABEL = 'label';
	const ARG_DESCRIPTION = 'description';
	const ARG_CATEGORY = 'category';
	const ARG_INPUT_SCHEMA = 'input_schema';
	const ARG_OUTPUT_SCHEMA = 'output_schema';
	const ARG_EXECUTE_CALLBACK = 'execute_callback';
	const ARG_PERMISSION_CALLBACK = 'permission_callback';
	const ARG_META = 'meta';
	const ARG_SHOW_IN_REST = 'show_in_rest';
	const ARG_MCP = 'mcp';
	const ARG_PUBLIC = 'public';

	const SCHEMA_TYPE = 'type';
	const SCHEMA_PROPERTIES = 'properties';
	const SCHEMA_ADDITIONAL_PROPERTIES = 'additionalProperties';
	const SCHEMA_DEFAULT = 'default';
	const SCHEMA_MINIMUM = 'minimum';
	const SCHEMA_MAXIMUM = 'maximum';
	const SCHEMA_TYPE_OBJECT = 'object';
	const SCHEMA_TYPE_INTEGER = 'integer';

	const RESPONSE_MESSAGE = 'message';
	const RESPONSE_DATA = 'data';

	const ERROR_INVALID = 'jetbackup_invalid_ability';
	const ERROR_FAILED = 'jetbackup_ability_failed';
	const MESSAGE_FAILED = 'JetBackup ability execution failed';

	const SANITIZE_OUTPUT = [
		'getSystemInfo' => ['secured_dir', 'data_dir', 'wordpress_path', 'jetbackup_data_dir'],
	];

	const ABILITIES = [
		'listBackups'      => ['List Backups',      'List available backup snapshots.',   self::INPUT_PAGINATION],
		'listBackupJobs'   => ['List Backup Jobs',  'List configured backup jobs.',       self::INPUT_PAGINATION],
		'listDestinations' => ['List Destinations', 'List configured backup destinations.', self::INPUT_PAGINATION],
		'listSchedules'    => ['List Schedules',    'List configured backup schedules.',  self::INPUT_PAGINATION],
		'listQueueItems'   => ['List Queue Items',  'List queue items.',                  self::INPUT_PAGINATION],
		'getBackup'        => ['Get Backup',        'Get a single backup snapshot by id.', self::INPUT_ID],
		'getBackupJob'     => ['Get Backup Job',    'Get a single backup job by id.',     self::INPUT_ID],
		'getQueueItem'     => ['Get Queue Item',    'Get a single queue item by id.',     self::INPUT_ID],
		'getDashboard'     => ['Get Dashboard',     'Get the dashboard summary.',         self::INPUT_NONE],
		'getSystemInfo'    => ['Get System Info',   'Get system information.',            self::INPUT_NONE],
	];

	private function __construct() {}

	public static function registerCategories(): void {

		try {
			if (!Factory::getSettingsSecurity()->isAbilitiesEnabled()) return;
		} catch (\Throwable $e) {
			return;
		}

		Wordpress::registerAbilityCategory(self::CATEGORY, [
			self::ARG_LABEL       => self::CATEGORY_LABEL,
			self::ARG_DESCRIPTION => self::CATEGORY_DESCRIPTION,
		]);
	}

	public static function register(): void {

		try {
			if (!Factory::getSettingsSecurity()->isAbilitiesEnabled()) return;
		} catch (\Throwable $e) {
			return;
		}

		foreach (self::ABILITIES as $action => $ability) {

			[$label, $description, $input] = $ability;

			Wordpress::registerAbility(self::_name($action), [
				self::ARG_LABEL               => $label,
				self::ARG_DESCRIPTION         => $description,
				self::ARG_CATEGORY            => self::CATEGORY,
				self::ARG_INPUT_SCHEMA        => self::_inputSchema($input),
				self::ARG_OUTPUT_SCHEMA       => [self::SCHEMA_TYPE => self::SCHEMA_TYPE_OBJECT],
				self::ARG_EXECUTE_CALLBACK    => static function ($input=[]) use ($action) { return self::_execute($action, is_array($input) ? $input : []); },
				self::ARG_PERMISSION_CALLBACK => [self::class, 'permission'],
				self::ARG_META                => [self::ARG_SHOW_IN_REST => true, self::ARG_MCP => [self::ARG_PUBLIC => true]],
			]);
		}
	}

	private static function _name(string $action): string {
		return self::CATEGORY . '/' . strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $action));
	}

	public static function permission(): bool {
		try {
			if (!function_exists('current_user_can') || !function_exists('is_user_logged_in')) return false;
			if (!is_user_logged_in()) return false;
			if (!current_user_can(self::CAPABILITY)) return false;
			if (Helper::isMultisite() && !Helper::isNetworkAdminUser()) return false;

			$security = Factory::getSettingsSecurity();
			if (!$security->isAbilitiesEnabled()) return false;
			if ($security->isMFAEnabled() && !UI::validateMFA() && !$security->isMFAAllowAbilities()) return false;

			return true;

		} catch (\Throwable $e) {
			if (Wordpress::isDebugModeEnabled()) error_log('[JetBackup] ability permission check failed: ' . $e->getMessage());
			return false;
		}
	}

	private static function _inputSchema(int $input): array {

		$properties = [];

		if ($input === self::INPUT_PAGINATION) $properties = [
			self::KEY_SKIP  => [self::SCHEMA_TYPE => self::SCHEMA_TYPE_INTEGER, self::SCHEMA_MINIMUM => 0],
			self::KEY_LIMIT => [
				self::SCHEMA_TYPE    => self::SCHEMA_TYPE_INTEGER,
				self::SCHEMA_MINIMUM => 1,
				self::SCHEMA_MAXIMUM => self::LIMIT_MAX,
				self::SCHEMA_DEFAULT => self::LIMIT_DEFAULT,
			],
		];
		else if ($input === self::INPUT_ID) $properties = [
			JetBackup::ID_FIELD => [self::SCHEMA_TYPE => self::SCHEMA_TYPE_INTEGER, self::SCHEMA_MINIMUM => 1],
		];

		return [
			self::SCHEMA_TYPE                  => self::SCHEMA_TYPE_OBJECT,
			self::SCHEMA_PROPERTIES            => $properties,
			self::SCHEMA_ADDITIONAL_PROPERTIES => false,
			self::SCHEMA_DEFAULT               => (object) [],
		];
	}

	private static function _execute(string $action, array $input) {

		$class = "\\JetBackup\\Ajax\\Calls\\" . ucfirst($action);

		if (!class_exists($class)) return new \WP_Error(self::ERROR_INVALID, 'Unknown JetBackup ability', ['status' => 404]);

		try {
			/** @var iAjax $call */
			$call = new $class();
			$call->setData(self::_boundInput($action, $input));
			$call->execute();

			$data = $call->getResponseData();
			if (isset(self::SANITIZE_OUTPUT[$action])) $data = self::_sanitize($data, self::SANITIZE_OUTPUT[$action]);

			return [
				self::RESPONSE_MESSAGE => $call->getResponseMessage(),
				self::RESPONSE_DATA    => $data,
			];

		} catch (JBException $e) {
			if (Wordpress::isDebugModeEnabled()) error_log('[JetBackup] ability failed (' . $action . '): ' . $e->getMessage());
			return new \WP_Error(self::ERROR_FAILED, self::MESSAGE_FAILED, ['status' => 400]);
		} catch (\Throwable $e) {
			if (Wordpress::isDebugModeEnabled()) error_log('[JetBackup] ability error (' . $action . '): ' . $e->getMessage());
			return new \WP_Error(self::ERROR_FAILED, self::MESSAGE_FAILED, ['status' => 500]);
		}
	}

	private static function _boundInput(string $action, array $input): array {
		if ((self::ABILITIES[$action][2] ?? self::INPUT_NONE) !== self::INPUT_PAGINATION) return $input;
		$limit = isset($input[self::KEY_LIMIT]) ? (int) $input[self::KEY_LIMIT] : self::LIMIT_DEFAULT;
		if ($limit < 1) $limit = self::LIMIT_DEFAULT;
		if ($limit > self::LIMIT_MAX) $limit = self::LIMIT_MAX;
		$input[self::KEY_LIMIT] = $limit;
		return $input;
	}

	private static function _sanitize(array $data, array $keys): array {
		foreach ($data as $key => $value) {
			if (in_array($key, $keys, true)) { unset($data[$key]); continue; }
			if (is_array($value)) $data[$key] = self::_sanitize($value, $keys);
		}
		return $data;
	}
}
