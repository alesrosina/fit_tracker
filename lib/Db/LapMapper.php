<?php

declare(strict_types=1);

namespace OCA\FitTracker\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class LapMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'fit_tracker_laps', Lap::class);
    }

    /** @return Lap[] */
    public function findByActivity(int $activityId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('activity_id', $qb->createNamedParameter($activityId, IQueryBuilder::PARAM_INT)))
            ->orderBy('lap_number', 'ASC');

        return $this->findEntities($qb);
    }

    public function deleteByActivity(int $activityId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('activity_id', $qb->createNamedParameter($activityId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }
}
