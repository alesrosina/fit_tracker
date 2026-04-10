<?php

declare(strict_types=1);

namespace OCA\FitTracker\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class SleepMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'fit_tracker_sleep', Sleep::class);
    }

    /** @return Sleep[] */
    public function findAllForUser(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->orderBy('start_time', 'DESC');

        return $this->findEntities($qb);
    }

    public function findForUser(int $id, string $userId): Sleep {
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

    public function findByFitPath(string $userId, string $fitFilePath): Sleep {
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
