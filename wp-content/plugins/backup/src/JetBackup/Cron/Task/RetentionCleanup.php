<?php

namespace JetBackup\Cron\Task;

use JetBackup\Data\Engine;
use JetBackup\Destination\Destination;
use JetBackup\Destination\Vendors\Imported\Imported;
use JetBackup\Exception\DBException;
use JetBackup\Exception\TaskException;
use JetBackup\Factory;
use JetBackup\JetBackup;
use JetBackup\Queue\Queue;
use JetBackup\Queue\QueueItem;
use JetBackup\Schedule\Schedule;
use JetBackup\Snapshot\Snapshot;
use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Exceptions\IOException;

if (!defined( '__JETBACKUP__')) die('Direct access is not allowed');

class RetentionCleanup extends Task {

	const LOG_FILENAME = 'retention';

	public function __construct() {
		parent::__construct(self::LOG_FILENAME);
	}

	/**
	 * @return void
	 * @throws TaskException
	 * @throws IOException
	 * @throws InvalidArgumentException
	 */
	public function execute():void {
		parent::execute();
		
		if($this->getQueueItem()->getStatus() == Queue::STATUS_PENDING) {
			$this->getLogController()->logMessage("Starting snapshots retention cleanup");

			$this->getQueueItem()->getProgress()->setTotalItems(count(Queue::STATUS_CLEANUP_NAMES)+3);
			$this->getQueueItem()->save();
			$this->getQueueItem()->updateProgress('Starting cleanup');
		} else if($this->getQueueItem()->getStatus() > Queue::STATUS_PENDING) {
			$this->getLogController()->logMessage('Resumed snapshots retention cleanup');
		}

		try {
			$this->func([$this, '_markManualSnapshots']);
			$this->func([$this, '_markImportedSnapshots']);
			$this->func([$this, '_deleteSnapshots']);
			if($this->getQueueItem()->getStatus() < Queue::STATUS_DONE && !$this->getQueueItem()->getErrors()) $this->getQueueItem()->updateStatus(Queue::STATUS_DONE);
			else $this->getQueueItem()->updateStatus(Queue::STATUS_PARTIALLY);
			$this->getLogController()->logMessage('Completed!');
		} catch(\Exception $e) {
			$this->getLogController()->logError($e->getMessage());
			$this->getQueueItem()->addError();
			$this->getQueueItem()->updateStatus(Queue::STATUS_PARTIALLY);
		}

		$this->getQueueItem()->updateProgress(
			$this->getQueueItem()->getStatus() == Queue::STATUS_DONE
				? 'Cleanup Completed!'
				: ($this->getQueueItem()->getStatus() == Queue::STATUS_PARTIALLY
				? 'Completed with errors (see logs)'
				: 'Cleanup Failed!'),
			QueueItem::PROGRESS_LAST_STEP
		);

		$this->getLogController()->logMessage('Total time: ' . $this->getExecutionTimeElapsed());
	}

	/**
	 * get a list of manual snapshots limit results based on retention limit
	 *
	 * @param int $destinationID
	 *
	 * @return array
	 * @throws IOException
	 * @throws InvalidArgumentException
	 */
	static public function getManualSnapshots(int $destinationID): array {

		return Snapshot::query()
		               ->where([Snapshot::SCHEDULES, 'contains', Schedule::TYPE_MANUALLY])
		               ->where([Engine::ENGINE, '=', Engine::ENGINE_WP])
		               ->where([Snapshot::DESTINATION_ID, '=', $destinationID])
		               ->orderBy([Snapshot::CREATED => 'desc'])
		               ->skip(Factory::getSettingsGeneral()->getManualBackupsRetention())
		               ->getQuery()
		               ->fetch();
	}

	/**
	 * Mark manual snapshots for deletion per retention global settings
	 * @return void
	 * @throws IOException
	 * @throws InvalidArgumentException
	 * @throws DBException
	 */
	public function _markManualSnapshots(): void {

		if (!Factory::getSettingsGeneral()->getManualBackupsRetention()) {
			$this->getLogController()->logMessage('[_markManualSnapshots] Manual Backups retention disabled, skipping');
			return;
		}

		$this->getLogController()->logMessage('[_markManualSnapshots] Execution time: ' . $this->getExecutionTimeElapsed());
		$this->getLogController()->logMessage('[_markManualSnapshots] TTL time: ' . $this->getExecutionTimeLimit());

		$destinations = Destination::query()->select([JetBackup::ID_FIELD])->getQuery()->fetch();
		foreach($destinations as $id) {

			$destination = new Destination($id[JetBackup::ID_FIELD]);
			if (!$destination->getId()) continue;
			if (!$destination->isEnabled()) continue;
			if ($destination->isReadOnly()) continue;

			foreach(self::getManualSnapshots($destination->getId()) as $snapshot_details) {
				$snapshot = new Snapshot($snapshot_details[JetBackup::ID_FIELD]);
				$this->getLogController()->logDebug('Marking Manual snapshot for delete: ' . $snapshot->getName() . ' ' . $snapshot->getDestinationName() . ' [ID ' . $snapshot->getDestinationId() . ']');
				$snapshot->removeSchedule(Schedule::TYPE_MANUALLY);
				// if there is no more schedules assigned for this snapshot we need to delete it
				if(!sizeof($snapshot->getSchedules())) $snapshot->setDeleted(time());
				$snapshot->save();
			}

		}

	}

	/**
	 * Get a list of imported snapshots beyond the retention limit
	 *
	 * @return array
	 * @throws IOException
	 * @throws InvalidArgumentException
	 * @throws DBException
	 */
	static public function getImportedSnapshots(): array {

		$retentionLimit = Factory::getSettingsGeneral()->getImportedBackupsRetention();
		if (!$retentionLimit) return []; // 0 means disabled

		$importedDest = Destination::getImportedDestination();
		if (!$importedDest) return [];

		return Snapshot::query()
		               ->where([Snapshot::SCHEDULES, 'contains', Schedule::TYPE_IMPORTED])
		               ->where([Engine::ENGINE, '=', Engine::ENGINE_WP])
		               ->where([Snapshot::DESTINATION_ID, '=', $importedDest->getId()])
		               ->orderBy([Snapshot::CREATED => 'desc'])
		               ->skip($retentionLimit)
		               ->getQuery()
		               ->fetch();
	}

	/**
	 * Mark imported snapshots for deletion per retention limit
	 * @return void
	 * @throws IOException
	 * @throws InvalidArgumentException
	 * @throws DBException
	 */
	public function _markImportedSnapshots(): void {

		if (!Factory::getSettingsGeneral()->getImportedBackupsRetention()) {
			$this->getLogController()->logMessage('[_markImportedSnapshots] Imported Backups retention disabled, skipping');
			return;
		}

		$this->getLogController()->logMessage('[_markImportedSnapshots] Execution time: ' . $this->getExecutionTimeElapsed());
		$this->getLogController()->logMessage('[_markImportedSnapshots] TTL time: ' . $this->getExecutionTimeLimit());

		$importedDest = Destination::getImportedDestination();
		if (!$importedDest) {
			$this->getLogController()->logMessage('[_markImportedSnapshots] No imported destination found, skipping');
			return;
		}

		foreach(self::getImportedSnapshots() as $snapshot_details) {
			$snapshot = new Snapshot($snapshot_details[JetBackup::ID_FIELD]);
			$this->getLogController()->logDebug('Marking Imported snapshot for delete: ' . $snapshot->getName() . ' ' . $snapshot->getDestinationName() . ' [ID ' . $snapshot->getDestinationId() . ']');
			$snapshot->removeSchedule(Schedule::TYPE_IMPORTED);
            $snapshot->removeSchedule(Schedule::TYPE_MANUALLY);
			// if there is no more schedules assigned for this snapshot we need to delete it
			if(!sizeof($snapshot->getSchedules())) $snapshot->setDeleted(time());
			$snapshot->save();
		}

	}

	/**
	 * @return array
	 * @throws IOException
	 * @throws InvalidArgumentException
	 */
	static public function listSnapshots(): array {

		return Snapshot::query()
			->where([Snapshot::DELETED, '>', 0])
			->where([Snapshot::DELETED, '<=', time()])
			->where([Engine::ENGINE, '=', Engine::ENGINE_WP])
			->orderBy([Snapshot::DESTINATION_ID => 'asc'])
			->getQuery()
			->fetch();

	}

	/**
	 * @return void
	 */
	public function _deleteSnapshots() {


		$this->getLogController()->logMessage('[_deleteSnapshots] Execution time: ' . $this->getExecutionTimeElapsed());
		$this->getLogController()->logMessage('[_deleteSnapshots] TTL time: ' . $this->getExecutionTimeLimit());

		$this->getQueueItem()->updateStatus(Queue::STATUS_CLEANUP_DELETING);
		$this->getQueueItem()->updateProgress('Deleting snapshots');

		$destinations = [];
		
		$this->foreachCallable(['JetBackup\Cron\Task\RetentionCleanup', 'listSnapshots'], [], function($i, $snapshot_details) use ($destinations) {

			try {
				$snapshot = new Snapshot($snapshot_details[JetBackup::ID_FIELD]);

				if(!isset($destinations[$snapshot->getDestinationId()]))
					$destinations[$snapshot->getDestinationId()] = new Destination($snapshot->getDestinationId());

				$destination = $destinations[$snapshot->getDestinationId()];

				$this->getLogController()->logMessage("Deleting snapshot \"{$snapshot->getName()}\" from destination \"{$destination->getName()}\"");
				$destination->removeDir($snapshot->getJobIdentifier() . '/' . $snapshot->getName());
				$snapshot->delete();
			} catch (\Exception $e) {
				$this->getLogController()->logError("Error deleting snapshot \"{$snapshot->getName()}\" from destination \"{$destination->getName()}\"");
				$this->getLogController()->logError($e->getMessage());
				$this->getQueueItem()->addError();
				return;
			}


		});
	}
}