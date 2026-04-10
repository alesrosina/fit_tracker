<?php

declare(strict_types=1);

namespace OCA\FitTracker\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class SleepStageMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'fit_tracker_sleep_stages', SleepStage::class);
    }

    /** @return SleepStage[] */
    public function findBySleep(int $sleepId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('sleep_id', $qb->createNamedParameter($sleepId, IQueryBuilder::PARAM_INT)))
            ->orderBy('timestamp', 'ASC');

        return $this->findEntities($qb);
    }

    public function deleteBySleep(int $sleepId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('sleep_id', $qb->createNamedParameter($sleepId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

    /** @param array<array{sleep_id: int, timestamp: string, stage: string}> $rows */
    public function insertBulk(array $rows): void {
        foreach ($rows as $row) {
            $qb = $this->db->getQueryBuilder();
            $qb->insert($this->getTableName())
                ->values([
                    'sleep_id'  => $qb->createNamedParameter($row['sleep_id'], IQueryBuilder::PARAM_INT),
                    'timestamp' => $qb->createNamedParameter($row['timestamp']),
                    'stage'     => $qb->createNamedParameter($row['stage']),
                ]);
            $qb->executeStatement();
        }
    }
}
