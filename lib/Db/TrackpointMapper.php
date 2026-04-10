<?php

declare(strict_types=1);

namespace OCA\FitTracker\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class TrackpointMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'fit_tracker_tp', Trackpoint::class);
    }

    /** @return Trackpoint[] */
    public function findByActivity(int $activityId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('activity_id', $qb->createNamedParameter($activityId, IQueryBuilder::PARAM_INT)))
            ->orderBy('timestamp', 'ASC');

        return $this->findEntities($qb);
    }

    public function deleteByActivity(int $activityId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('activity_id', $qb->createNamedParameter($activityId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

    /**
     * Insert many trackpoints in bulk for performance.
     * @param array<array{activity_id: int, timestamp: string, lat: ?float, lon: ?float, altitude: ?float, distance: ?float, heart_rate: ?int, speed: ?float, cadence: ?int, power: ?int}> $rows
     */
    public function insertBulk(array $rows): void {
        foreach ($rows as $row) {
            $qb = $this->db->getQueryBuilder();
            $qb->insert($this->getTableName())
                ->values([
                    'activity_id' => $qb->createNamedParameter($row['activity_id'], IQueryBuilder::PARAM_INT),
                    'timestamp'   => $qb->createNamedParameter($row['timestamp']),
                    'lat'         => $qb->createNamedParameter($row['lat']),
                    'lon'         => $qb->createNamedParameter($row['lon']),
                    'altitude'    => $qb->createNamedParameter($row['altitude']),
                    'distance'    => $qb->createNamedParameter($row['distance']),
                    'heart_rate'  => $qb->createNamedParameter($row['heart_rate'], IQueryBuilder::PARAM_INT),
                    'speed'       => $qb->createNamedParameter($row['speed']),
                    'cadence'     => $qb->createNamedParameter($row['cadence'], IQueryBuilder::PARAM_INT),
                    'power'       => $qb->createNamedParameter($row['power'], IQueryBuilder::PARAM_INT),
                ]);
            $qb->executeStatement();
        }
    }
}
