<?php

declare(strict_types=1);

namespace OCA\FitTracker\Service;

use OCA\FitTracker\Db\Activity;
use OCA\FitTracker\Db\ActivityMapper;

/**
 * Numbers across activities: heart rate zones and training load per activity,
 * and personal records. Built on AnalysisService's stored results.
 */
class StatsService {
    // Lower bounds of zones 1-5 as a share of max HR
    public const ZONE_FRACTIONS = [0.5, 0.6, 0.7, 0.8, 0.9];
    private const RECORDS_PER_ENTRY = 3;

    public function __construct(
        private ActivityMapper  $activityMapper,
        private SettingsService $settings,
    ) {}

    /**
     * Max and resting HR to use: the user's settings, else the highest heart
     * rate ever recorded (when plausible) and a typical resting rate.
     *
     * @return array{maxHr: int, restingHr: int, maxHrIsDefault: bool, restingHrIsDefault: bool, zoneBounds: int[]}
     */
    public function heartRateSettings(string $userId, ?array $activities = null): array {
        $maxHr = $this->settings->get($userId, 'max_hr');
        $maxIsDefault = $maxHr === null;
        if ($maxHr === null) {
            $observed = 0;
            foreach ($activities ?? $this->activityMapper->findAllForUser($userId) as $a) {
                $observed = max($observed, (int) $a->getMaxHr());
            }
            $maxHr = $observed >= 120 ? $observed : SettingsService::DEFAULT_MAX_HR;
        }
        $restingHr = $this->settings->get($userId, 'resting_hr');
        $restingIsDefault = $restingHr === null;
        $restingHr ??= SettingsService::DEFAULT_RESTING_HR;

        return [
            'maxHr' => $maxHr,
            'restingHr' => $restingHr,
            'maxHrIsDefault' => $maxIsDefault,
            'restingHrIsDefault' => $restingIsDefault,
            'zoneBounds' => array_map(fn($f) => (int) round($maxHr * $f), self::ZONE_FRACTIONS),
        ];
    }

    /**
     * Time in each HR zone and TRIMP per activity, oldest first. Activities
     * without heart rate data are left out.
     *
     * @return array{settings: array, activities: array}
     */
    public function load(string $userId): array {
        $activities = $this->activityMapper->findAllWithAnalysis($userId);
        $hr = $this->heartRateSettings($userId, $activities);

        $rows = [];
        foreach ($activities as $a) {
            $hist = $a->getAnalysisData()['hr'] ?? null;
            if (!$hist) {
                continue;
            }
            $rows[] = [
                'id'        => $a->getId(),
                'startTime' => $a->getStartTime(),
                'sport'     => $a->getSport(),
                'zones'     => self::zoneSeconds($hist, $hr['zoneBounds']),
                'trimp'     => round(self::trimp($hist, $hr['maxHr'], $hr['restingHr']), 1),
            ];
        }
        return ['settings' => $hr, 'activities' => $rows];
    }

    /** @param array<int|string, int> $hist bpm => seconds */
    public static function zoneSeconds(array $hist, array $bounds): array {
        $zones = [0, 0, 0, 0, 0];
        foreach ($hist as $bpm => $seconds) {
            for ($z = 4; $z >= 0; $z--) {
                if ($bpm >= $bounds[$z]) {
                    $zones[$z] += $seconds;
                    break;
                }
            }
        }
        return $zones;
    }

    /**
     * Banister TRIMP: minutes × HR reserve fraction × 0.64·e^(1.92·fraction).
     * Uses the commonly used 1.92 weighting for every user.
     */
    public static function trimp(array $hist, int $maxHr, int $restingHr): float {
        $reserve = max(1, $maxHr - $restingHr);
        $sum = 0.0;
        foreach ($hist as $bpm => $seconds) {
            $fraction = min(1.0, max(0.0, ((int) $bpm - $restingHr) / $reserve));
            $sum += $seconds / 60 * $fraction * 0.64 * exp(1.92 * $fraction);
        }
        return $sum;
    }

    /**
     * Fastest efforts per distance, best power per duration, and longest
     * distance / duration / biggest climb per sport — the top three of each.
     */
    public function records(string $userId): array {
        $efforts = [];   // family => meters => entries
        $power   = [];   // seconds => entries
        $longest = [];   // sport => metric => entries

        foreach ($this->activityMapper->findAllWithAnalysis($userId) as $a) {
            $data = $a->getAnalysisData() ?? [];
            $family = AnalysisService::effortFamily($a->getSport());
            if ($family !== null) {
                foreach ($data['efforts'] ?? [] as $meters => $seconds) {
                    $efforts[$family][$meters][] = $this->entry($a, (float) $seconds);
                }
            }
            foreach ($data['power'] ?? [] as $duration => $watts) {
                $power[$duration][] = $this->entry($a, (float) $watts);
            }
            foreach (['distance' => $a->getDistance(), 'duration' => $a->getDuration(), 'elevation' => $a->getElevationGain()] as $metric => $value) {
                if ($value) {
                    $longest[$a->getSport()][$metric][] = $this->entry($a, (float) $value);
                }
            }
        }

        $top = function (array $entries, bool $lowestFirst): array {
            usort($entries, fn($x, $y) => $lowestFirst ? $x['value'] <=> $y['value'] : $y['value'] <=> $x['value']);
            return array_slice($entries, 0, self::RECORDS_PER_ENTRY);
        };

        $out = ['efforts' => [], 'power' => [], 'longest' => []];
        foreach (AnalysisService::EFFORT_DISTANCES as $family => $distances) {
            foreach ($distances as $meters) {
                $key = AnalysisService::distanceKey($meters);
                if (!empty($efforts[$family][$key])) {
                    $out['efforts'][$family][] = ['meters' => $meters, 'top' => $top($efforts[$family][$key], true)];
                }
            }
        }
        foreach (AnalysisService::POWER_DURATIONS as $duration) {
            if (!empty($power[$duration])) {
                $out['power'][] = ['seconds' => $duration, 'top' => $top($power[$duration], false)];
            }
        }
        ksort($longest);
        foreach ($longest as $sport => $metrics) {
            $row = ['sport' => $sport];
            foreach ($metrics as $metric => $entries) {
                $row[$metric] = $top($entries, false);
            }
            $out['longest'][] = $row;
        }
        return $out;
    }

    private function entry(Activity $a, float $value): array {
        return [
            'activityId' => $a->getId(),
            'name'       => $a->getName(),
            'startTime'  => $a->getStartTime(),
            'value'      => $value,
        ];
    }

    /**
     * Downsampled GPS tracks of all activities for the heatmap.
     * @return array<array{id: int, sport: string, track: array}>
     */
    public function heatmap(string $userId, ?string $sport): array {
        $out = [];
        foreach ($this->activityMapper->findAllWithAnalysis($userId, $sport) as $a) {
            $track = $a->getAnalysisData()['track'] ?? null;
            if ($track) {
                $out[] = ['id' => $a->getId(), 'sport' => $a->getSport(), 'name' => $a->getName(), 'track' => $track];
            }
        }
        return $out;
    }
}
