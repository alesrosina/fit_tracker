<?php

declare(strict_types=1);

namespace OCA\FitTracker\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IDBConnection;
use OCP\User\Events\UserDeletedEvent;

/**
 * Removes everything FIT Tracker stored for an account when it is deleted.
 * The .fit files themselves go with the user's files.
 *
 * @template-implements IEventListener<UserDeletedEvent>
 */
class UserDeletedListener implements IEventListener {
    public function __construct(private IDBConnection $db) {}

    public function handle(Event $event): void {
        if (!($event instanceof UserDeletedEvent)) {
            return;
        }
        $userId = $event->getUser()->getUID();

        // Child rows first: trackpoints, laps and stages are keyed by their parent's id
        $this->deleteChildren('fit_tracker_tp', 'activity_id', 'fit_tracker_activities', $userId);
        $this->deleteChildren('fit_tracker_laps', 'activity_id', 'fit_tracker_activities', $userId);
        $this->deleteChildren('fit_tracker_sleep_stages', 'sleep_id', 'fit_tracker_sleep', $userId);

        foreach (['fit_tracker_activities', 'fit_tracker_sleep', 'fit_tracker_meta', 'fit_tracker_goals', 'fit_tracker_gear', 'fit_tracker_shares'] as $table) {
            $qb = $this->db->getQueryBuilder();
            $qb->delete($table)->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
            $qb->executeStatement();
        }
    }

    private function deleteChildren(string $table, string $column, string $parent, string $userId): void {
        $ids = $this->db->getQueryBuilder();
        $ids->select('id')->from($parent)->where($ids->expr()->eq('user_id', $ids->createNamedParameter($userId)));
        $result = $ids->executeQuery();
        $parentIds = array_map('intval', $result->fetchAll(\PDO::FETCH_COLUMN));
        $result->closeCursor();

        foreach (array_chunk($parentIds, 500) as $chunk) {
            $qb = $this->db->getQueryBuilder();
            $qb->delete($table)->where($qb->expr()->in($column, $qb->createNamedParameter($chunk, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT_ARRAY)));
            $qb->executeStatement();
        }
    }
}
