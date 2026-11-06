<?php

declare(strict_types=1);

namespace OCA\FitTracker\Service;

use OCA\FitTracker\Db\Activity;
use OCA\FitTracker\Db\ActivityMapper;
use OCA\FitTracker\Db\Lap;
use OCA\FitTracker\Db\LapMapper;
use OCA\FitTracker\Db\MetaMapper;
use OCA\FitTracker\Db\Share;
use OCA\FitTracker\Db\ShareMapper;
use OCA\FitTracker\Db\Trackpoint;
use OCA\FitTracker\Db\TrackpointMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;

/**
 * Public read-only links to single activities. The public page gets the
 * stats, laps and route, never the file path, notes or photos. With
 * "hide start and end", the first and last HIDE_METERS of the route are left
 * off the map, so it doesn't show where someone lives.
 */
class ShareService {
    public const HIDE_METERS = 200;
    private const PUBLIC_POINTS = 1500;

    public function __construct(
        private ShareMapper      $shareMapper,
        private ActivityMapper   $activityMapper,
        private LapMapper        $lapMapper,
        private TrackpointMapper $trackpointMapper,
        private MetaMapper       $metaMapper,
        private ISecureRandom    $random,
        private IURLGenerator    $urlGenerator,
        private IUserManager     $userManager,
        private SettingsService  $settings,
    ) {}

    /** @return array[] links of one activity */
    public function listForActivity(Activity $activity, string $userId): array {
        if ($activity->getFileId() === null) {
            return [];
        }
        return array_map(fn(Share $s) => $this->toArray($s, $activity), $this->shareMapper->findForFile($userId, $activity->getFileId()));
    }

    /** @return array[] all links of the user, with the activity they point to */
    public function listForUser(string $userId): array {
        $out = [];
        foreach ($this->shareMapper->findAllForUser($userId) as $share) {
            try {
                $activity = $this->activityMapper->findByFileId($userId, $share->getFileId());
            } catch (DoesNotExistException) {
                $activity = null;
            }
            $out[] = $this->toArray($share, $activity);
        }
        return $out;
    }

    /** @throws \RuntimeException when the activity has no known file id */
    public function create(Activity $activity, string $userId, bool $hideEnds): array {
        if ($activity->getFileId() === null) {
            throw new \RuntimeException('Activity file not found');
        }
        $share = new Share();
        $share->setUserId($userId);
        $share->setFileId($activity->getFileId());
        $share->setToken($this->random->generate(24, ISecureRandom::CHAR_ALPHANUMERIC));
        $share->setHideEnds($hideEnds);
        $share->setCreatedAt((new \DateTime())->format('Y-m-d H:i:s'));
        return $this->toArray($this->shareMapper->insert($share), $activity);
    }

    /** @throws DoesNotExistException */
    public function delete(int $id, string $userId): void {
        $this->shareMapper->delete($this->shareMapper->findForUser($id, $userId));
    }

    public function deleteForActivity(Activity $activity, string $userId): void {
        if ($activity->getFileId() !== null) {
            $this->shareMapper->deleteForFile($userId, $activity->getFileId());
        }
    }

    /**
     * Everything the public page shows, or null for an unknown token or an
     * activity that no longer exists.
     */
    public function publicData(string $token): ?array {
        try {
            $share = $this->shareMapper->findByToken($token);
            $activity = $this->activityMapper->findByFileId($share->getUserId(), $share->getFileId());
        } catch (DoesNotExistException) {
            return null;
        }

        $meta = $this->metaMapper->findForFile($share->getUserId(), $share->getFileId());
        $owner = $this->userManager->get($share->getUserId());

        $a = $activity->toArray();
        unset($a['fitFilePath'], $a['fileId'], $a['createdAt']);
        if ($meta?->getCustomName()) {
            $a['name'] = $meta->getCustomName();
        }

        $points = array_map(fn(Trackpoint $tp) => $tp->toArray(), $this->trackpointMapper->findByActivity($activity->getId()));
        if ($share->getHideEnds()) {
            $points = self::hideEnds($points, self::HIDE_METERS);
        }

        return [
            'activity'    => $a,
            'laps'        => array_map(fn(Lap $l) => $l->toArray(), $this->lapMapper->findByActivity($activity->getId())),
            'trackpoints' => self::downsample($points, self::PUBLIC_POINTS),
            'owner'       => $owner?->getDisplayName() ?? '',
            'hideEnds'    => $share->getHideEnds(),
            // Shown in the owner's units; viewers have no settings of their own
            'units'       => $this->settings->get($share->getUserId(), 'units'),
        ];
    }

    private function toArray(Share $share, ?Activity $activity): array {
        return [
            'id'         => $share->getId(),
            'token'      => $share->getToken(),
            'url'        => $this->urlGenerator->linkToRouteAbsolute('fit_tracker.public.show', ['token' => $share->getToken()]),
            'hideEnds'   => $share->getHideEnds(),
            'createdAt'  => $share->getCreatedAt(),
            'activityId' => $activity?->getId(),
            'activityName' => $activity?->getName(),
        ];
    }

    /**
     * Drop the position (not the other readings) of points within $meters of
     * the start or the end of the route, measured along the GPS track.
     */
    public static function hideEnds(array $points, float $meters): array {
        $gps = [];
        foreach ($points as $i => $p) {
            if ($p['lat'] !== null && $p['lon'] !== null) {
                $gps[] = $i;
            }
        }
        if (count($gps) < 2) {
            return $points;
        }
        $cum = [0.0];
        for ($k = 1; $k < count($gps); $k++) {
            $a = $points[$gps[$k - 1]];
            $b = $points[$gps[$k]];
            $cum[] = $cum[$k - 1] + self::haversine($a['lat'], $a['lon'], $b['lat'], $b['lon']);
        }
        $total = end($cum);
        foreach ($gps as $k => $i) {
            if ($cum[$k] < $meters || $total - $cum[$k] < $meters) {
                $points[$i]['lat'] = null;
                $points[$i]['lon'] = null;
            }
        }
        return $points;
    }

    private static function downsample(array $points, int $max): array {
        $n = count($points);
        if ($n <= $max) {
            return $points;
        }
        $out = [];
        $step = $n / $max;
        for ($x = 0; $x < $n; $x += $step) {
            $out[] = $points[(int) $x];
        }
        return $out;
    }

    private static function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
