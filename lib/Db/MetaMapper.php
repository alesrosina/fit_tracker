<?php

declare(strict_types=1);

namespace OCA\FitTracker\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class MetaMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'fit_tracker_meta', Meta::class);
    }

    /** @return array<int, Meta> keyed by file id */
    public function findAllForUser(string $userId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        $byFile = [];
        foreach ($this->findEntities($qb) as $meta) {
            $byFile[$meta->getFileId()] = $meta;
        }
        return $byFile;
    }

    public function findForFile(string $userId, int $fileId): ?Meta {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException) {
            return null;
        }
    }

    /** Existing row for the file, or a new unsaved one. */
    public function findOrNew(string $userId, int $fileId): Meta {
        $meta = $this->findForFile($userId, $fileId);
        if ($meta !== null) {
            return $meta;
        }
        $meta = new Meta();
        $meta->setUserId($userId);
        $meta->setFileId($fileId);
        return $meta;
    }

    public function save(Meta $meta): Meta {
        return $meta->getId() === null ? $this->insert($meta) : $this->update($meta);
    }

    /** Point activities that used a deleted piece of gear back to the sport default. */
    public function clearGear(string $userId, int $gearId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update($this->getTableName())
            ->set('gear_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('gear_id', $qb->createNamedParameter($gearId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

    public function deleteForFile(string $userId, int $fileId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }
}
