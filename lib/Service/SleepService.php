<?php

declare(strict_types=1);

namespace OCA\FitTracker\Service;

use OCA\FitTracker\AppInfo\Application;
use OCA\FitTracker\Db\Sleep;
use OCA\FitTracker\Db\SleepMapper;
use OCA\FitTracker\Db\SleepStage;
use OCA\FitTracker\Db\SleepStageMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;

class SleepService {

    public function __construct(
        private SleepMapper        $sleepMapper,
        private SleepStageMapper   $sleepStageMapper,
        private SleepParserService $sleepParser,
        private IRootFolder        $rootFolder,
        private IConfig            $config,
    ) {}

    /** @return array[] */
    public function listForUser(string $userId): array {
        return array_map(
            fn(Sleep $s) => $s->toArray(),
            $this->sleepMapper->findAllForUser($userId)
        );
    }

    public function getForUser(int $id, string $userId): array {
        return $this->sleepMapper->findForUser($id, $userId)->toArray();
    }

    public function getStagesForUser(int $id, string $userId): array {
        $this->sleepMapper->findForUser($id, $userId); // auth check
        return array_map(
            fn(SleepStage $s) => $s->toArray(),
            $this->sleepStageMapper->findBySleep($id)
        );
    }

    public function deleteForUser(int $id, string $userId): void {
        $this->sleepMapper->findForUser($id, $userId); // auth check
        $this->sleepStageMapper->deleteBySleep($id);
        $this->sleepMapper->deleteForUser($id, $userId);
    }

    /**
     * Scan configured folder for sleep FIT files, import new ones, remove orphans.
     * Returns ['imported' => int, 'skipped' => int, 'removed' => int, 'errors' => string[]]
     */
    public function syncFolder(string $userId): array {
        $stats = ['imported' => 0, 'skipped' => 0, 'removed' => 0, 'errors' => []];

        $folderPath = $this->config->getUserValue($userId, Application::APP_ID, 'folder_path', '');
        if ($folderPath === '') {
            return $stats;
        }

        $userFolder     = $this->rootFolder->getUserFolder($userId);
        $userFolderPath = rtrim($userFolder->getPath(), '/');
        if (str_starts_with($folderPath, $userFolderPath)) {
            $folderPath = substr($folderPath, strlen($userFolderPath));
        }
        $folderPath = ltrim($folderPath, '/');

        try {
            $node = $userFolder->get($folderPath);
        } catch (NotFoundException) {
            throw new \RuntimeException("Folder not found: $folderPath");
        }

        if (!($node instanceof Folder)) {
            throw new \RuntimeException("Path is not a folder: $folderPath");
        }

        $fitFiles   = $this->collectFitFiles($node);
        $currentSet = [];
        foreach ($fitFiles as $file) {
            $relativePath             = ltrim(substr($file->getPath(), strlen($userFolder->getPath())), '/');
            $currentSet[$relativePath] = $file;
        }

        $existingPaths = $this->sleepMapper->findAllFitPathsForUser($userId);
        $existingSet   = array_flip($existingPaths);

        foreach ($currentSet as $relativePath => $file) {
            if (isset($existingSet[$relativePath])) {
                $stats['skipped']++;
                continue;
            }
            try {
                $imported = $this->importFile($userId, $file, $relativePath);
                if ($imported) {
                    $stats['imported']++;
                }
                // $imported === false means it was an activity file, not counted
            } catch (\Exception $e) {
                $stats['errors'][] = $file->getName() . ': ' . $e->getMessage();
            }
        }

        // Remove orphans
        foreach ($existingPaths as $dbPath) {
            if (!isset($currentSet[$dbPath])) {
                try {
                    $sleep = $this->sleepMapper->findByFitPath($userId, $dbPath);
                    $this->sleepStageMapper->deleteBySleep($sleep->getId());
                    $this->sleepMapper->deleteForUser($sleep->getId(), $userId);
                    $stats['removed']++;
                } catch (\Exception) {
                    // Already gone
                }
            }
        }

        return $stats;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Import a single file. Returns true if imported as sleep, false if skipped
     * (not a sleep file).
     */
    private function importFile(string $userId, \OCP\Files\File $file, string $relativePath): bool {
        $tmpPath = tempnam(sys_get_temp_dir(), 'fit_sleep_');
        try {
            file_put_contents($tmpPath, $file->getContent());

            if (!$this->sleepParser->isSleepFile($tmpPath)) {
                return false;
            }

            $parsed = $this->sleepParser->parse($tmpPath);
        } finally {
            @unlink($tmpPath);
        }

        $sleep = new Sleep();
        $sleep->setUserId($userId);
        $sleep->setName($parsed['name']);
        $sleep->setStartTime($parsed['start_time']);
        $sleep->setEndTime($parsed['end_time']);
        $sleep->setDuration($parsed['duration']);
        $sleep->setScore($parsed['score']);
        $sleep->setHrvScore($parsed['hrv_score']);
        $sleep->setTimeDeep($parsed['time_deep']);
        $sleep->setTimeLight($parsed['time_light']);
        $sleep->setTimeRem($parsed['time_rem']);
        $sleep->setTimeAwake($parsed['time_awake']);
        $sleep->setFitFilePath($relativePath);
        $sleep->setCreatedAt((new \DateTime())->format('Y-m-d H:i:s'));

        $sleep = $this->sleepMapper->insert($sleep);

        $rows = array_map(
            fn($stage) => ['sleep_id' => $sleep->getId(), 'timestamp' => $stage['timestamp'], 'stage' => $stage['stage']],
            $parsed['stages']
        );
        $this->sleepStageMapper->insertBulk($rows);

        return true;
    }

    /** @return \OCP\Files\File[] */
    private function collectFitFiles(Folder $folder): array {
        $results = [];
        foreach ($folder->getDirectoryListing() as $node) {
            if ($node instanceof Folder) {
                array_push($results, ...$this->collectFitFiles($node));
            } elseif (strtolower(pathinfo($node->getName(), PATHINFO_EXTENSION)) === 'fit') {
                $results[] = $node;
            }
        }
        return $results;
    }
}
