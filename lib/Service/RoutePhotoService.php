<?php

declare(strict_types=1);

namespace OCA\FitTracker\Service;

use OCA\FitTracker\Db\ActivityMapper;
use OCA\FitTracker\Db\Trackpoint;
use OCA\FitTracker\Db\TrackpointMapper;
use OCP\ICache;
use OCP\ICacheFactory;

/**
 * Photos along all of a user's routes, for the heatmap. Matching reads the
 * EXIF data of each candidate photo (PhotoService), so it works through the
 * activities a few seconds per request with a cursor, and caches the result
 * per activity for a while.
 */
class RoutePhotoService {
    private const BUDGET_SECONDS = 6.0;
    private const CACHE_TTL = 6 * 3600;

    private ICache $cache;

    public function __construct(
        private ActivityMapper   $activityMapper,
        private TrackpointMapper $trackpointMapper,
        private PhotoService     $photoService,
        ICacheFactory            $cacheFactory,
    ) {
        $this->cache = $cacheFactory->createDistributed('fit_tracker_photos');
    }

    /**
     * Photos of the activities from position $cursor on (newest first), as far
     * as the time budget allows.
     *
     * @return array{photos: array[], next: ?int, total: int} next is null when done
     */
    public function batch(string $userId, ?string $sport, int $cursor): array {
        $activities = array_values(array_filter(
            $this->activityMapper->findAllForUser($userId),
            fn($a) => $sport === null || $a->getSport() === $sport,
        ));
        $start = microtime(true);
        $photos = [];
        $i = max(0, $cursor);
        for ($n = count($activities); $i < $n; $i++) {
            // Always get at least one activity done per request
            if ($i > $cursor && microtime(true) - $start > self::BUDGET_SECONDS) {
                break;
            }
            $a = $activities[$i];
            foreach ($this->photosFor($userId, $a) as $photo) {
                $photos[] = $photo + ['activityId' => $a->getId(), 'sport' => $a->getSport()];
            }
        }
        return [
            'photos' => $photos,
            'next'   => $i < count($activities) ? $i : null,
            'total'  => count($activities),
        ];
    }

    private function photosFor(string $userId, \OCA\FitTracker\Db\Activity $a): array {
        // The file id is part of the key: a re-imported activity is matched again
        $key = $userId . '/' . $a->getId() . '/' . ($a->getFileId() ?? 0);
        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            return $cached;
        }
        $photos = [];
        // Cheap check first: most activities without GPS need no trackpoints at all
        if ($this->trackpointMapper->findFirstPosition($a->getId()) !== null) {
            $points = array_map(fn(Trackpoint $tp) => ['lat' => $tp->getLat(), 'lon' => $tp->getLon()], $this->trackpointMapper->findByActivity($a->getId()));
            $photos = $this->photoService->getPhotosForActivity($userId, $a->getStartTime(), $a->getDuration(), $points);
        }
        $this->cache->set($key, $photos, self::CACHE_TTL);
        return $photos;
    }
}
