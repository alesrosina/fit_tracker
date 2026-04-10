<?php

declare(strict_types=1);

namespace OCA\FitTracker\Service;

use OCA\FitTracker\AppInfo\Application;
use OCA\FitTracker\Db\Activity;
use OCA\FitTracker\Db\ActivityMapper;
use OCA\FitTracker\Db\Lap;
use OCA\FitTracker\Db\LapMapper;
use OCA\FitTracker\Db\TrackpointMapper;
use OCA\FitTracker\Service\SleepParserService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;

class ActivityService {

    public function __construct(
        private ActivityMapper    $activityMapper,
        private LapMapper         $lapMapper,
        private TrackpointMapper  $trackpointMapper,
        private FitParserService  $fitParser,
        private SleepParserService $sleepParser,
        private IRootFolder       $rootFolder,
        private IConfig           $config,
    ) {}

    /** @return array[] */
    public function listForUser(string $userId): array {
        return array_map(
            fn(Activity $a) => $a->toArray(),
            $this->activityMapper->findAllForUser($userId)
        );
    }

    public function getForUser(int $id, string $userId): array {
        return $this->activityMapper->findForUser($id, $userId)->toArray();
    }

    public function getLapsForUser(int $id, string $userId): array {
        $this->activityMapper->findForUser($id, $userId);
        return array_map(
            fn(Lap $l) => $l->toArray(),
            $this->lapMapper->findByActivity($id)
        );
    }

    public function getTrackpointsForUser(int $id, string $userId): array {
        $this->activityMapper->findForUser($id, $userId);
        return array_map(
            fn(\OCA\FitTracker\Db\Trackpoint $t) => $t->toArray(),
            $this->trackpointMapper->findByActivity($id)
        );
    }

    public function getFolderPath(string $userId): string {
        return $this->config->getUserValue($userId, Application::APP_ID, 'folder_path', '');
    }

    public function setFolderPath(string $userId, string $path): void {
        $this->config->setUserValue($userId, Application::APP_ID, 'folder_path', $path);
    }

    /**
     * Scan the configured folder for .fit files, import new ones, and remove
     * DB records for files that no longer exist.
     *
     * Returns ['imported' => int, 'skipped' => int, 'removed' => int, 'errors' => string[]]
     * Returns empty stats (no throw) when no folder is configured.
     */
    public function syncFolder(string $userId): array {
        $stats = ['imported' => 0, 'skipped' => 0, 'removed' => 0, 'errors' => []];

        $folderPath = $this->getFolderPath($userId);
        if ($folderPath === '') {
            return $stats;
        }

        $userFolder = $this->rootFolder->getUserFolder($userId);

        // Strip the user home prefix if the file picker returned an absolute path
        // e.g. "/admin/files/Activities" -> "Activities"
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

        $fitFiles = $this->collectFitFiles($node);

        // Build map: relativePath => File
        $currentSet = [];
        foreach ($fitFiles as $file) {
            $relativePath = ltrim(substr($file->getPath(), strlen($userFolder->getPath())), '/');
            $currentSet[$relativePath] = $file;
        }

        $existingPaths = $this->activityMapper->findAllFitPathsForUser($userId);
        $existingSet   = array_flip($existingPaths);

        // Import new files
        foreach ($currentSet as $relativePath => $file) {
            if (isset($existingSet[$relativePath])) {
                $stats['skipped']++;
                continue;
            }
            try {
                $this->importFile($userId, $file, $relativePath);
                $stats['imported']++;
            } catch (\Exception $e) {
                $stats['errors'][] = $file->getName() . ': ' . $e->getMessage();
            }
        }

        // Remove orphans (recorded in DB but file no longer present)
        foreach ($existingPaths as $dbPath) {
            if (!isset($currentSet[$dbPath])) {
                try {
                    $activity = $this->activityMapper->findByFitPath($userId, $dbPath);
                    $this->trackpointMapper->deleteByActivity($activity->getId());
                    $this->lapMapper->deleteByActivity($activity->getId());
                    $this->activityMapper->deleteForUser($activity->getId(), $userId);
                    $stats['removed']++;
                } catch (\Exception) {
                    // Already gone or not found — ignore
                }
            }
        }

        return $stats;
    }

    /**
     * Parse the first .fit file in the configured folder and return its raw
     * session/record structure for debugging.
     */
    public function debugFirstFile(string $userId, FitParserService $parser): array {
        $folderPath = $this->getFolderPath($userId);
        if ($folderPath === '') {
            return ['error' => 'No folder configured'];
        }
        $userFolder     = $this->rootFolder->getUserFolder($userId);
        $userFolderPath = rtrim($userFolder->getPath(), '/');
        if (str_starts_with($folderPath, $userFolderPath)) {
            $folderPath = substr($folderPath, strlen($userFolderPath));
        }
        $folderPath = ltrim($folderPath, '/');
        $node = $userFolder->get($folderPath);
        $files = $this->collectFitFiles($node);
        if (empty($files)) {
            return ['error' => 'No .fit files found in folder'];
        }
        $file   = $files[0];
        $tmpPath = tempnam(sys_get_temp_dir(), 'fit_debug_');
        try {
            file_put_contents($tmpPath, $file->getContent());
            return ['file' => $file->getName(), 'dump' => $parser->debugDump($tmpPath)];
        } finally {
            @unlink($tmpPath);
        }
    }

    public function deleteForUser(int $id, string $userId): void {
        $this->activityMapper->findForUser($id, $userId);
        $this->trackpointMapper->deleteByActivity($id);
        $this->lapMapper->deleteByActivity($id);
        $this->activityMapper->deleteForUser($id, $userId);
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

    private function importFile(string $userId, \OCP\Files\File $file, string $relativePath): void {
        // Write to a temp file so FitParserService can read it
        $tmpPath = tempnam(sys_get_temp_dir(), 'fit_tracker_');
        try {
            file_put_contents($tmpPath, $file->getContent());
            // Skip sleep files — they are handled by SleepService
            if ($this->sleepParser->isSleepFile($tmpPath)) {
                return;
            }
            $parsed = $this->fitParser->parse($tmpPath);
        } finally {
            @unlink($tmpPath);
        }

        $activity = new Activity();
        $activity->setUserId($userId);
        $activity->setName($parsed['name']);
        $activity->setSport($parsed['sport']);
        $activity->setStartTime($parsed['start_time']);
        $activity->setDuration($parsed['duration']);
        $activity->setDistance($parsed['distance']);
        $activity->setElevationGain($parsed['elevation_gain']);
        $activity->setAvgHr($parsed['avg_hr']);
        $activity->setMaxHr($parsed['max_hr']);
        $activity->setCalories($parsed['calories']);
        $activity->setAvgSpeed($parsed['avg_speed']);
        $activity->setMaxSpeed($parsed['max_speed']);
        $activity->setAvgCadence($parsed['avg_cadence']);
        $activity->setAvgPower($parsed['avg_power']);
        $activity->setFitFilePath($relativePath);
        $activity->setCreatedAt((new \DateTime())->format('Y-m-d H:i:s'));

        $activity = $this->activityMapper->insert($activity);

        foreach ($parsed['laps'] as $lapData) {
            $lap = new Lap();
            $lap->setActivityId($activity->getId());
            $lap->setLapNumber($lapData['lap_number']);
            $lap->setStartTime($lapData['start_time']);
            $lap->setDuration($lapData['duration']);
            $lap->setDistance($lapData['distance']);
            $lap->setAvgHr($lapData['avg_hr']);
            $lap->setMaxHr($lapData['max_hr']);
            $lap->setAvgSpeed($lapData['avg_speed']);
            $lap->setElevationGain($lapData['elevation_gain']);
            $lap->setCalories($lapData['calories']);
            $this->lapMapper->insert($lap);
        }

        $rows = array_map(fn($tp) => array_merge(['activity_id' => $activity->getId()], $tp), $parsed['trackpoints']);
        $this->trackpointMapper->insertBulk($rows);
    }
}
