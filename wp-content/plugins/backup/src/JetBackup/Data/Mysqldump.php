<?php

namespace JetBackup\Data;

use Exception;
use JetBackup\Factory;
use JetBackup\Filesystem\AtomicWrite;
use JetBackup\Log\LogController;
use Mysqldump\Mysqldump as MysqldumpAlias;
use PDO;
use PDOException;

if (!defined( '__JETBACKUP__')) die('Direct access is not allowed');

class Mysqldump extends MysqldumpAlias {

	private ArrayData $_data;
	private LogController $_logController;

	const QUERY_MAX_RETRIES = 10;
	const SQLSTATE_ERRORS   = ['08S01', '08001', '40001', 'HY000'];

	/***
	 * These SQLSTATE errors indicate connection failures and trigger a retry.
	 * - '08S01' → Communication link failure (e.g., network disconnect, timeout, or dropped connection)
	 * - '08001' → Client unable to establish a connection (e.g., incorrect credentials, DNS issues)
	 * - '40001' → Transaction deadlock detected (e.g., deadlock errors requiring retry)
	 * - 'HY000' → General MySQL error (covers various issues, including MySQL server disconnects)
	 */

	/**
	 * @throws Exception
	 */
	public function __construct($db_name, $db_user, $db_password, $db_host) {

		$this->_data = new ArrayData();

		$this->_setDBName($db_name);
		$this->_setDBPassword($db_password);
		$this->_setDBUser($db_user);
		$this->_setDBHost($db_host);

		parent::__construct(
			'mysql:host='.$this->getDBHost().';port='.$this->getDBPort().';dbname='.$this->getDBName(),
			$this->getDBUser(),
			$this->getDBPassword(),
			[
				'compress'               => MysqldumpAlias::NONE,
				'add-drop-table'         => true,
				'if-not-exists'          => true,
				'reset-auto-increment'   => true,
				'complete-insert'        => true,
				'default-character-set'  => MysqldumpAlias::UTF8MB4,
				'extended-insert'        => false,
				'insert-ignore'          => true,
				'lock-tables'            => false,
				'init_commands'          => [
					// Snapshot current mode safely (NULL-safe)
					"SET @jb_sql_mode := IFNULL(@@SESSION.sql_mode, '');",

					// Remove modes that commonly break dumps
					"SET SESSION sql_mode = TRIM(BOTH ',' FROM
        REPLACE(
        REPLACE(
        REPLACE(
        REPLACE(
        REPLACE(
            CONCAT(',', @jb_sql_mode, ','),
            ',ONLY_FULL_GROUP_BY,', ','
        ),
            ',STRICT_TRANS_TABLES,', ','
        ),
            ',STRICT_ALL_TABLES,', ','
        ),
            ',NO_ZERO_DATE,', ','
        ),
            ',TRADITIONAL,', ','
        )
    );",
				],
			]
		);
	}

	public function setDumpSetting($key, $value): void { $this->dumpSettings[$key] = $value; }
	public function getDumpSetting($key, $default = null) { return $this->dumpSettings[$key] ?? $default; }
	public function set($key, $value) { $this->_data->set($key, $value); }
	public function get($key, $default = '') {return $this->_data->get($key, $default);}
	public function setLogController(LogController $log) {$this->_logController = $log;}

	private function getLogController(): LogController {
		if (!isset($this->_logController)) $this->_logController = new LogController();
		return $this->_logController;
	}

	/**
	 * Check if the given object is a VIEW in the current database.
	 *
	 * @param string $name Table or view name
	 * @return bool true if it's a VIEW, false otherwise
	 * @throws Exception on query failure
	 */
	public function _isView(string $name): bool {
		if (!$this->dbHandler) {
			$this->connect();
		}

		$sql = "SELECT TABLE_TYPE 
              FROM INFORMATION_SCHEMA.TABLES 
             WHERE TABLE_SCHEMA = :db 
               AND TABLE_NAME = :name 
             LIMIT 1";

		$stmt = $this->dbHandler->prepare($sql);
		$stmt->execute([
			':db'   => $this->getDBName(),
			':name' => $name
		]);

		$type = $stmt->fetchColumn();

		return ($type === 'VIEW');
	}

	private function _setDBHost($db_host) {
		if (strpos($db_host, ':') !== false) {
			$parts   = explode(':', $db_host, 2);
			$db_host = $parts[0] ?? 'localhost';
			$this->_setDBPort((int) ($parts[1] ?? 3306));
		}
		$this->set('db_host', $db_host);
	}

	public function getDBHost() { return $this->get('db_host'); }
	private function _setDBPort($db_port) { $this->set('db_port', $db_port); }
	public function getDBPort() { return $this->get('db_port', Factory::getSettingsGeneral()->getMySQLDefaultPort()); }
	private function _setDBName($db_name) { $this->set('db_name', $db_name); }
	public function getDBName() { return $this->get('db_name'); }
	private function _setDBUser($db_user) { $this->set('db_user', $db_user); }
	public function getDBUser() { return $this->get('db_user'); }
	private function _setDBPassword($db_password) { $this->set('db_password', $db_password); }
	public function getDBPassword() { return $this->get('db_password'); }

	/**
	 * @throws Exception
	 */
	public function setInclude(array $include) {

		$name = $include[0] ?? null;

		// reset first
		$this->setDumpSetting('include-tables', []);
		$this->setDumpSetting('include-views', []);
		$this->setDumpSetting('exclude-tables', []);

		if (!$name) return;

		$isView = $this->_isView($name);

		if ($isView) {
			$this->setDumpSetting('include-views', [$name]);
			$pattern = '/^(?!' . preg_quote($name, '/') . '$).*/';
			$this->setDumpSetting('exclude-tables', [$pattern]);
			$this->setDumpSetting('skip-triggers', true);
			$this->setDumpSetting('add-drop-table', false);
			$this->setDumpSetting('no-create-info', false);
		} else {
			$this->setDumpSetting('include-tables', [$name]);
			$this->setDumpSetting('include-views', [$name]);
			$this->setDumpSetting('exclude-tables', []);
		}
	}

	public function getInclude() { return $this->getDumpSetting('include-tables', []); }
	public function setExclude($exclude) { $this->setDumpSetting('no-data', $exclude); }
	public function getExclude() { return $this->getDumpSetting('no-data', []); }

	/**
	 * @param $buffer
	 * @return mixed|null
	 * Detect problematic SET commands that might involve '@OLD_' or '@saved_' variables
	 */
	private function _checkResume($buffer) {

		// Only a real SET statement uses the @OLD_/@saved_ session vars that may not survive the
		// import and need skipping. Check the statement's first word, not the whole text, so a value
		// like "... SET x=@OLD_FOO ..." inside an INSERT can't make us skip (and lose) that row.
		$head = SqlStatementParser::effectiveHead($buffer);

		if (strncasecmp($head, 'SET', 3) === 0 && preg_match('/^SET\b[\s\S]*=\s*@[\w_]+/i', $head)) {
			$this->getLogController()->logMessage("Skipping problematic statement: {$buffer}");
			return null; // Skip this query
		}

		return $buffer;
	}

	/**
	 * Override connect method with proper retry mechanism.
	 * @throws Exception
	 */
	protected function connect() {

		$attempts = 0;
		$waitTime = 500000; // Start with 500ms

		while ($attempts < self::QUERY_MAX_RETRIES) {
			try {
				$this->getLogController()->logDebug("Attempting to reconnect to MySQL (Attempt #$attempts)");

				parent::connect();

				if ($this->dbHandler) {
					$this->getLogController()->logDebug("Successfully reconnected to MySQL.");
					return;
				}

			} catch (Exception $e) {
				$this->getLogController()->logMessage("Reconnect attempt #$attempts failed (SQLSTATE: {$e->getCode()}): " . $e->getMessage());

				if (in_array($e->getCode(), self::SQLSTATE_ERRORS)) {
					$this->getLogController()->logMessage("Connection error detected (SQLSTATE: {$e->getCode()}), destroying dbHandler...");
					$this->dbHandler = null;
				}

				if ($attempts >= self::QUERY_MAX_RETRIES - 1) {
					throw new Exception("MySQL reconnect failed after $attempts attempts: " . $e->getMessage());
				}

				usleep($waitTime);
				$waitTime = min($waitTime * 2, 60000000); // max 60s
			}

			$attempts++;
		}
	}

	/**
	 * Import SQL file with resumable progress tracking.
	 *
	 * @throws Exception
	 */
	public function import($path) {

		try {

			if (!$path || !is_file($path)) throw new Exception("[import] File {$path} does not exist.");

			$_table            = basename($path);
			$_progress_file    = $path . ".progress";
			$_progress_position = file_exists($_progress_file) ? (int) file_get_contents($_progress_file) : 0;

			$handle = fopen($path, 'rb');
			if (!$handle) throw new Exception("Failed reading file {$path}. Check access permissions.");

			if (!$this->dbHandler) $this->connect();

			// BEFORE ANY CHANGES:
			$this->query_exec("SET @jb_prev_sql_mode := @@SESSION.sql_mode;");
			$this->query_exec("SET @jb_prev_fk := @@SESSION.FOREIGN_KEY_CHECKS;");
			$this->query_exec("SET SESSION FOREIGN_KEY_CHECKS=0;");

			// Add NO_ENGINE_SUBSTITUTION without clobbering others
			$this->query_exec("
			  SET SESSION sql_mode = TRIM(BOTH ',' FROM
			    CONCAT_WS(',',
			      REPLACE(REPLACE(@@SESSION.sql_mode, ',NO_ENGINE_SUBSTITUTION', ''), 'NO_ENGINE_SUBSTITUTION', ''),
			      'NO_ENGINE_SUBSTITUTION'
			    )
			  );
			");

			// Relax strict/only_full_group_by
			$this->query_exec("
			  SET SESSION sql_mode = TRIM(BOTH ',' FROM
			    REPLACE(REPLACE(REPLACE(
			      CONCAT(',', IFNULL(@@SESSION.sql_mode,''), ','),
			      ',ONLY_FULL_GROUP_BY,', ','
			    ), ',STRICT_TRANS_TABLES,', ','
			    ), ',STRICT_ALL_TABLES,', ','
			    )
			  );
			");

			if ($_progress_position > 0) {
				fseek($handle, $_progress_position);
				$this->getLogController()->logMessage("Resuming import for {$_table}, Position: {$_progress_position}");
			} else {
				$this->getLogController()->logMessage("Starting new import for: {$_table}");
			}

			// The parser splits statements while ignoring quotes and comments, so a ';' (or a
			// CREATE/VIEW/DEFINER= word) inside a quoted value is never read as SQL. It also handles
			// comment/blank lines, so we don't pre-skip lines by hand anymore.
			$parser = new SqlStatementParser();

			while (!feof($handle)) {
				$lineRaw = fgets($handle);
				if ($lineRaw === false) break;

				foreach ($parser->feed($lineRaw) as $rawStmt) {
					$this->_importStatement($rawStmt);
				}

				// Save the resume position only when we're between statements, so a resume always
				// starts on a whole statement.
				if (!$parser->hasPending()) {
					try {
						AtomicWrite::write($_progress_file, (string) ftell($handle), $this->getLogController());
					} catch (Exception $e) {
						$this->getLogController()->logError("Failed to update progress file: " . $e->getMessage());
						// keep going even if the progress file can't be written
					}
				}
			}

			// A last statement with no ';' at the end (some dumps skip it).
			if (($rawStmt = $parser->flush()) !== null) {
				$this->_importStatement($rawStmt);
			}

			fclose($handle);
			$this->getLogController()->logMessage("Finished importing {$_table}");

			if (is_file($_progress_file)) {
				@unlink($_progress_file);
				$this->getLogController()->logMessage("Progress file for table removed");
			}

		} catch (Exception $e) {
			$this->getLogController()->logMessage("Error: " . $e->getMessage());
			throw new Exception($e->getMessage());
		} finally {
			// Best-effort restore of previous mode
			try { $this->query_exec("SET SESSION sql_mode = @jb_prev_sql_mode;"); } catch (\Exception $e) {}
			try { $this->query_exec("SET SESSION FOREIGN_KEY_CHECKS=@jb_prev_fk;"); } catch (\Exception $e) {}
			try { $this->query_exec("SET SESSION sql_mode = @jb_prev_sql_mode;"); } catch (\Exception $e) {}
		}
	}

	/**
	 * Run one statement from the dump: skip the session-restore SETs, fix up a real CREATE VIEW so it
	 * restores on another server, then run it. A statement counts as a view only when it truly starts
	 * with CREATE ... VIEW, so an INSERT that just mentions those words runs as-is.
	 *
	 * @throws Exception
	 */
	private function _importStatement(string $rawStmt): void {
		try {
			$stmt = $this->_checkResume($rawStmt);
			if ($stmt === null) return;

			if (SqlStatementParser::isCreateView($stmt)) {
				$stmt = SqlStatementParser::normalizeCreateView($stmt);
			}

			$this->query_exec($stmt);
		} catch (PDOException $e) {
			$this->getLogController()->logMessage("Failed to execute query: {$rawStmt}");
			$this->getLogController()->logMessage("Error: " . $e->getMessage());
			$this->getLogController()->logMessage("SQLSTATE: " . $e->getCode());

			throw new Exception("Failed to execute query: {$rawStmt}");
		}
	}

	/**
	 * Check if a table exists in the current database.
	 *
	 * @param string $name         Table name (unquoted)
	 * @param bool   $includeViews If true, treat views as existing "tables" as well
	 * @return bool
	 * @throws Exception
	 */
	public function tableExists(string $name, bool $includeViews = true): bool {

		if ($name === '') return false;

		$attempt  = 0;
		$waitTime = 500000; // 0.5s
		$sql = "SELECT 1 
		          FROM INFORMATION_SCHEMA.TABLES 
		         WHERE TABLE_SCHEMA = :db 
		           AND TABLE_NAME   = :name" . ($includeViews ? "" : " AND TABLE_TYPE = 'BASE TABLE'") . " 
		         LIMIT 1";

		while ($attempt < self::QUERY_MAX_RETRIES) {
			try {
				if (!$this->dbHandler) $this->connect();

				$stmt = $this->dbHandler->prepare($sql);
				$stmt->execute([
					':db'   => $this->getDBName(),
					':name' => $name,
				]);

				return (bool) $stmt->fetchColumn();

			} catch (\PDOException $e) {
				$sqlState = (string) $e->getCode();

				if (in_array($sqlState, self::SQLSTATE_ERRORS, true)) {
					$this->getLogController()->logMessage("tableExists(): SQLSTATE {$sqlState}, retrying (attempt #{$attempt})...");
					$this->connect();
					usleep($waitTime);
					$waitTime = min($waitTime * 2, 60000000);
					$attempt++;
					continue;
				}

				throw new Exception("Failed checking existence for table '{$name}' [SQLSTATE {$sqlState}]: " . $e->getMessage(), 0, $e);
			}
		}

		return false;
	}

	/**
	 * Execute a query that returns a result set (e.g., SELECT, SHOW TABLES).
	 *
	 * @param string $query  The SQL query to execute.
	 * @param array  $params Bound parameters
	 *
	 * @return array|null Returns an array of results or null on failure.
	 * @throws Exception
	 */
	public function query_exec(string $query, array $params = []): ?array {
		$waitTime = 500000; // 0.5s
		$attempt  = 0;

		$is_dml        = (bool) preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b/i', $query);
		$is_ddl_or_set = (bool) preg_match('/^\s*(CREATE|ALTER|DROP|RENAME|TRUNCATE|GRANT|REVOKE|ANALYZE|OPTIMIZE|REPAIR|SET)\b/i', $query);

		$begin_tx = $is_dml && !$is_ddl_or_set;

		$retryable_sqlstates   = ['08S01'];
		$retryable_drivercodes = [2006, 2013, 1205, 1213];

		while ($attempt < self::QUERY_MAX_RETRIES) {
			try {
				if (!$this->dbHandler) {
					$this->getLogController()->logMessage("No database handler, connecting...");
					$this->connect();
				}

				if ($begin_tx) {
					if ($this->dbHandler->inTransaction()) {
						$this->getLogController()->logMessage("Warning: already in txn, committing previous one.");
						try { $this->dbHandler->commit(); } catch (\Throwable $ignore) {}
					}
					$this->getLogController()->logMessage(
						"Starting transaction for: " . (strlen($query) > 90 ? substr($query,0,87) . "..." : $query)
					);
					$this->dbHandler->beginTransaction();
				}

				$stmt = $this->dbHandler->prepare($query);
				$ok   = $stmt->execute($params);

				if ($ok) {
					if ($begin_tx && $this->dbHandler->inTransaction()) {
						$this->getLogController()->logMessage(
							"Committing transaction for: " . (strlen($query) > 90 ? substr($query,0,87) . "..." : $query)
						);
						$this->dbHandler->commit();
					}
					return $stmt->fetchAll(PDO::FETCH_OBJ);
				}

				$this->getLogController()->logMessage("Query execution returned false.");
				return null;

			} catch (PDOException $e) {
				if ($begin_tx && $this->dbHandler && $this->dbHandler->inTransaction()) {
					$this->getLogController()->logMessage("Rolling back transaction due to error.");
					try { $this->dbHandler->rollBack(); } catch (\Throwable $ignore) {}
				}

				$sqlState   = (string) $e->getCode();
				$errInfo    = property_exists($e, 'errorInfo') ? (array) $e->errorInfo : [];
				$driverCode = $errInfo[1] ?? null;
				$driverMsg  = $errInfo[2] ?? '';
				$snippet    = (strlen($query) > 300) ? substr($query,0,297) . '...' : $query;

				$this->getLogController()->logError(
					"[SQL ERROR] SQLSTATE={$sqlState} driverCode=" . var_export($driverCode,true) .
					" msg=" . $e->getMessage() . " driverMsg=" . $driverMsg .
					" | Query: {$snippet} | Params: " . json_encode($params)
				);

				$retryable = in_array($sqlState, $retryable_sqlstates, true)
				             || (is_int($driverCode) && in_array($driverCode, $retryable_drivercodes, true));

				if ($retryable) {
					$this->getLogController()->logMessage("Transient DB error; reconnecting and retrying (attempt {$attempt}).");
					try { $this->connect(); } catch (\Throwable $ignore) {}
					usleep($waitTime);
					$waitTime = min($waitTime * 2, 60000000);
					$attempt++;
					continue;
				}

				throw new Exception(
					"Query error [SQLSTATE {$sqlState}" . ($driverCode !== null ? "/{$driverCode}" : "") . "]: " . $e->getMessage(),
					0,
					$e
				);
			}
		}

		$this->getLogController()->logError("Max retries reached for query.");
		return null;
	}
}
