<?php

declare(strict_types=1);

namespace OCA\FitTracker\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ActivityMapper extends QBMapper {
    // Everything except the analysis JSON, which can be several KB per row
    private const LIST_COLUMNS = [
        'id', 'user_id', 'name', 'sport', 'start_time', 'duration', 'distance', 'elevation_gain',
        'avg_hr', 'max_hr', 'calories', 'avg_speed', 'max_speed', 'avg_cadence', 'avg_power',
        'fit_file_path', 'created_at', 'file_id', 'analysis_version',
    ];

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'fit_tracker_activities', Activity::class);
    }

    /**
     * All of the user's activities, newest first, without the analysis column.
     * @return Activity[]
     */
    public function findAllForUser(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select(self::LIST_COLUMNS)
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->orderBy('start_time', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * All of the user's activities including the analysis JSON, oldest first.
     * @return Activity[]
     */
    public function findAllWithAnalysis(string $userId, ?string $sport = null): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->orderBy('start_time', 'ASC');
        if ($sport !== null) {
            $qb->andWhere($qb->expr()->eq('sport', $qb->createNamedParameter($sport)));
        }
        return $this->findEntities($qb);
    }

    /** @return Activity[] activities whose analysis is missing or outdated */
    public function findPendingAnalysis(string $userId, int $version): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select(self::LIST_COLUMNS)
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->lt('analysis_version', $qb->createNamedParameter($version, IQueryBuilder::PARAM_INT)))
            ->orderBy('start_time', 'DESC');
        return $this->findEntities($qb);
    }

    public function countPendingAnalysis(string $userId, int $version): int {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->count('id', 'n'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->lt('analysis_version', $qb->createNamedParameter($version, IQueryBuilder::PARAM_INT)));
        $result = $qb->executeQuery();
        $n = (int) $result->fetchOne();
        $result->closeCursor();
        return $n;
    }

    /** @throws DoesNotExistException */
    public function findByFileId(string $userId, int $fileId): Activity {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
            ->setMaxResults(1);
        return $this->findEntity($qb);
    }

    public function findForUser(int $id, string $userId): Activity {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        return $this->findEntity($qb);
    }

    /** @return string[] */
    public function findAllFitPathsForUser(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('fit_file_path')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        $result = $qb->executeQuery();
        $paths = [];
        while ($row = $result->fetch()) {
            $paths[] = $row['fit_file_path'];
        }
        $result->closeCursor();
        return $paths;
    }

    public function findByFitPath(string $userId, string $fitFilePath): Activity {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('fit_file_path', $qb->createNamedParameter($fitFilePath)));
        return $this->findEntity($qb);
    }

    public function deleteForUser(int $id, string $userId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        $qb->executeStatement();
    }
}
