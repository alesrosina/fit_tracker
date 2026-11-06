<?php

declare(strict_types=1);

namespace OCA\FitTracker\Service;

use OCA\FitTracker\Db\Activity;
use OCA\FitTracker\Db\ActivityMapper;
use OCA\FitTracker\Db\Trackpoint;
use OCA\FitTracker\Db\TrackpointMapper;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use Psr\Log\LoggerInterface;

/**
 * Per-activity numbers derived from the trackpoints, computed once at import
 * and stored as JSON in fit_tracker_activities.analysis:
 *
 *   hr      {bpm: seconds}        time spent at each heart rate, so HR zones
 *                                 and training load can be computed for any
 *                                 max/resting HR setting without re-reading points
 *   efforts {meters: seconds}     fastest time over standard distances
 *   power   {seconds: watts}      best average power over standard durations
 *   np      watts                 normalized power
 *   track   [[lat, lon], ...]     downsampled route for the heatmap
 *
 * Activities imported before this existed are analyzed in batches through
 * analyzePending(); bump VERSION to have every activity analyzed again.
 */
class AnalysisService {
    public const VERSION = 1;

    // A gap longer than this between two points is a pause, not time at that HR/power
    private const MAX_GAP_SECONDS = 30;
    private const TRACK_POINTS = 300;

    /** Distances (meters) for best efforts, by sport family. */
    public const EFFORT_DISTANCES = [
        'foot'  => [400, 1000, 1609.344, 5000, 10000, 21097.5, 42195],
        'cycle' => [5000, 10000, 20000, 40000, 100000],
    ];
    private const FOOT_SPORTS  = ['running', 'walking', 'hiking', 'trail_running'];
    private const CYCLE_SPORTS = ['cycling', 'e_biking', 'mountain_biking'];

    public const POWER_DURATIONS = [5, 15, 30, 60, 120, 300, 600, 1200, 1800, 3600];

    public function __construct(
        private ActivityMapper   $activityMapper,
        private TrackpointMapper $trackpointMapper,
        private IRootFolder      $rootFolder,
        private LoggerInterface  $logger,
    ) {}

    public static function effortFamily(string $sport): ?string {
        if (in_array($sport, self::FOOT_SPORTS, true)) {
            return 'foot';
        }
        if (in_array($sport, self::CYCLE_SPORTS, true)) {
            return 'cycle';
        }
        return null;
    }

    /**
     * @param array<array{timestamp: string, lat: ?float, lon: ?float, distance: ?float, heart_rate: ?int, power: ?int}> $points
     *        distance in km, as stored by FitParserService
     */
    public function analyze(array $points, string $sport): array {
        $times = array_map(fn($p) => strtotime($p['timestamp'] . ' UTC'), $points);

        $result = ['v' => self::VERSION];

        $hr = $this->hrHistogram($points, $times);
        if ($hr) {
            $result['hr'] = $hr;
        }

        $family = self::effortFamily($sport);
        if ($family !== null) {
            $efforts = $this->bestEfforts($points, $times, self::EFFORT_DISTANCES[$family]);
            if ($efforts) {
                $result['efforts'] = $efforts;
            }
        }

        $perSecond = $this->powerPerSecond($points, $times);
        if ($perSecond) {
            $result['power'] = $this->powerCurve($perSecond);
            $result['np']    = $this->normalizedPower($perSecond);
        }

        $track = $this->downsampleTrack($points);
        if ($track) {
            $result['track'] = $track;
        }

        return $result;
    }

    /**
     * Analyze activities that were imported before analysis existed (or with
     * an older VERSION), until the time budget runs out. Also fills in the
     * file id of rows imported before it was stored.
     *
     * @return array{analyzed: int, remaining: int}
     */
    public function analyzePending(string $userId, float $budgetSeconds = 8.0): array {
        $start = microtime(true);
        $analyzed = 0;
        $userFolder = $this->rootFolder->getUserFolder($userId);

        foreach ($this->activityMapper->findPendingAnalysis($userId, self::VERSION) as $activity) {
            if (microtime(true) - $start > $budgetSeconds) {
                break;
            }
            try {
                $this->analyzeStored($activity, $userFolder);
            } catch (\Throwable $e) {
                // Don't retry it forever; it gets analyzed again on re-import
                $this->logger->warning('FIT Tracker: could not analyze activity ' . $activity->getId() . ': ' . $e->getMessage());
                $activity->setAnalysis(json_encode(['v' => self::VERSION]));
                $activity->setAnalysisVersion(self::VERSION);
                $this->activityMapper->update($activity);
            }
            $analyzed++;
        }

        return [
            'analyzed'  => $analyzed,
            'remaining' => $this->activityMapper->countPendingAnalysis($userId, self::VERSION),
        ];
    }

    /** Analyze one activity now if it hasn't been yet, e.g. when its page is opened. */
    public function ensureAnalyzed(Activity $activity, string $userId): void {
        if ($activity->getAnalysisVersion() >= self::VERSION) {
            return;
        }
        try {
            $this->analyzeStored($activity, $this->rootFolder->getUserFolder($userId));
        } catch (\Throwable $e) {
            // The page works without it; analyzePending() tries again
            $this->logger->warning('FIT Tracker: could not analyze activity ' . $activity->getId() . ': ' . $e->getMessage());
        }
    }

    private function analyzeStored(Activity $activity, \OCP\Files\Folder $userFolder): void {
        if ($activity->getFileId() === null) {
            try {
                $node = $userFolder->get($activity->getFitFilePath());
                if ($node instanceof File) {
                    $activity->setFileId($node->getId());
                }
            } catch (NotFoundException) {
                // Removed from the folder; the next sync drops the activity
            }
        }

        $points = array_map(fn(Trackpoint $tp) => [
            'timestamp'  => $tp->getTimestamp(),
            'lat'        => $tp->getLat(),
            'lon'        => $tp->getLon(),
            'distance'   => $tp->getDistance(),
            'heart_rate' => $tp->getHeartRate(),
            'power'      => $tp->getPower(),
        ], $this->trackpointMapper->findByActivity($activity->getId()));

        $activity->setAnalysis(json_encode($this->analyze($points, $activity->getSport())));
        $activity->setAnalysisVersion(self::VERSION);
        $this->activityMapper->update($activity);
    }

    /** @return array<int, int> bpm => seconds */
    private function hrHistogram(array $points, array $times): array {
        $hist = [];
        for ($i = 1, $n = count($points); $i < $n; $i++) {
            $bpm = $points[$i]['heart_rate'];
            $dt  = $times[$i] - $times[$i - 1];
            if ($bpm === null || $bpm <= 0 || $dt <= 0 || $dt > self::MAX_GAP_SECONDS) {
                continue;
            }
            $hist[$bpm] = ($hist[$bpm] ?? 0) + $dt;
        }
        ksort($hist);
        return $hist;
    }

    /**
     * Fastest elapsed time over each distance, sliding a window over the
     * cumulative distance. The window start is interpolated between points so
     * sparse "smart recording" files don't overstate the time.
     *
     * @param float[] $targets meters
     * @return array<string, float> meters (as string key) => seconds
     */
    private function bestEfforts(array $points, array $times, array $targets): array {
        $d = [];
        $t = [];
        foreach ($points as $i => $p) {
            if ($p['distance'] === null) {
                continue;
            }
            $meters = $p['distance'] * 1000;
            // Distance only grows; skip readings that jump backwards
            if ($d && $meters < end($d)) {
                continue;
            }
            $d[] = $meters;
            $t[] = $times[$i];
        }
        $n = count($d);
        if ($n < 2) {
            return [];
        }

        $best = [];
        foreach ($targets as $target) {
            if ($d[$n - 1] - $d[0] < $target) {
                break; // targets are ascending
            }
            $min = null;
            $j = 0;
            for ($i = 1; $i < $n; $i++) {
                if ($d[$i] - $d[0] < $target) {
                    continue;
                }
                // Latest start j with d[i] - d[j] >= target
                while ($j + 1 < $i && $d[$i] - $d[$j + 1] >= $target) {
                    $j++;
                }
                $startDist = $d[$i] - $target;
                $span = $d[$j + 1] - $d[$j];
                $startTime = $span > 0
                    ? $t[$j] + ($t[$j + 1] - $t[$j]) * ($startDist - $d[$j]) / $span
                    : $t[$j];
                $elapsed = $t[$i] - $startTime;
                if ($elapsed > 0 && ($min === null || $elapsed < $min)) {
                    $min = $elapsed;
                }
            }
            if ($min !== null) {
                $best[self::distanceKey($target)] = round($min, 1);
            }
        }
        return $best;
    }

    public static function distanceKey(float $meters): string {
        return rtrim(rtrim(number_format($meters, 3, '.', ''), '0'), '.');
    }

    /**
     * Power resampled to one value per second: short gaps repeat the last
     * reading (devices record every 1-2 s), pauses count as zero.
     *
     * @return int[]
     */
    private function powerPerSecond(array $points, array $times): array {
        $samples = [];
        foreach ($points as $i => $p) {
            if ($p['power'] !== null) {
                $samples[] = [$times[$i], $p['power']];
            }
        }
        if (count($samples) < 2) {
            return [];
        }
        $out = [];
        for ($k = 0, $n = count($samples); $k < $n; $k++) {
            [$ts, $watts] = $samples[$k];
            $next = $k + 1 < $n ? $samples[$k + 1][0] : $ts + 1;
            $gap  = max(1, $next - $ts);
            $fill = $gap > 5 ? 1 : $gap;
            for ($s = 0; $s < $fill; $s++) {
                $out[] = $watts;
            }
            for ($s = $fill; $s < min($gap, 3600); $s++) {
                $out[] = 0;
            }
        }
        return $out;
    }

    /** @return array<int, int> seconds => best average watts */
    private function powerCurve(array $perSecond): array {
        $n = count($perSecond);
        $prefix = [0];
        foreach ($perSecond as $w) {
            $prefix[] = end($prefix) + $w;
        }
        $curve = [];
        foreach (self::POWER_DURATIONS as $dur) {
            if ($dur > $n) {
                break;
            }
            $max = 0;
            for ($i = $dur; $i <= $n; $i++) {
                $max = max($max, $prefix[$i] - $prefix[$i - $dur]);
            }
            $curve[$dur] = (int) round($max / $dur);
        }
        return $curve;
    }

    /** 30 s rolling average, raised to the 4th power, averaged, 4th root. */
    private function normalizedPower(array $perSecond): ?int {
        $n = count($perSecond);
        if ($n < 30) {
            return null;
        }
        $sum = 0;
        $window = array_sum(array_slice($perSecond, 0, 30));
        $count = 0;
        for ($i = 30; $i <= $n; $i++) {
            $sum += ($window / 30) ** 4;
            $count++;
            if ($i < $n) {
                $window += $perSecond[$i] - $perSecond[$i - 30];
            }
        }
        return (int) round(($sum / $count) ** 0.25);
    }

    /** @return array<array{0: float, 1: float}> */
    private function downsampleTrack(array $points): array {
        $gps = array_values(array_filter($points, fn($p) => $p['lat'] !== null && $p['lon'] !== null));
        $n = count($gps);
        if ($n < 2) {
            return [];
        }
        $step = max(1, $n / self::TRACK_POINTS);
        $track = [];
        for ($x = 0; $x < $n; $x += $step) {
            $p = $gps[(int) $x];
            $track[] = [round($p['lat'], 5), round($p['lon'], 5)];
        }
        $last = $gps[$n - 1];
        $track[] = [round($last['lat'], 5), round($last['lon'], 5)];
        return $track;
    }
}
