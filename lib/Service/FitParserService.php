<?php

declare(strict_types=1);

namespace OCA\FitTracker\Service;

use adriangibbons\phpFITFileAnalysis;
use Exception;

/**
 * Parses a .fit file and returns a structured result array.
 *
 * Uses adriangibbons/php-fit-file-analysis under the hood.
 *
 * Data structure from the library (columnar, NOT row-based):
 *   - data_mesgs['session']  → flat assoc array  ['sport' => 2, 'start_time' => 1744070000, ...]
 *   - data_mesgs['record']   → columnar:          ['timestamp' => [0=>ts1,1=>ts2,...], 'heart_rate' => [ts1=>120, ts2=>121,...], ...]
 *   - data_mesgs['lap']      → columnar:          ['timestamp' => [0=>ts1,1=>ts2,...], 'total_elapsed_time' => [0=>3600, 1=>1800,...], ...]
 *   Single-element arrays are automatically flattened to scalars by oneElementArrays().
 *   Timestamps are Unix epoch (library adds FIT_UNIX_TS_DIFF = 631065600 by default).
 */
class FitParserService {

    /**
     * Parse a FIT file and return structured activity data.
     *
     * @param string $filePath Absolute path to the .fit file
     * @return array{
     *   sport: string,
     *   name: string,
     *   start_time: string,
     *   duration: int,
     *   distance: ?float,
     *   elevation_gain: ?float,
     *   avg_hr: ?int,
     *   max_hr: ?int,
     *   calories: ?int,
     *   avg_speed: ?float,
     *   max_speed: ?float,
     *   avg_cadence: ?int,
     *   avg_power: ?int,
     *   laps: array,
     *   trackpoints: array,
     * }
     */
    public function parse(string $filePath): array {
        if (!file_exists($filePath)) {
            throw new Exception("FIT file not found: $filePath");
        }

        $fit     = new phpFITFileAnalysis($filePath, ['fix_data' => ['all'], 'units' => 'metric']);
        $session = $fit->data_mesgs['session'] ?? [];
        $sport   = $this->detectSport($fit, $session);
        $startTime = $this->resolveStartTime($fit, $session);

        return [
            'sport'         => $sport,
            'name'          => $this->buildName($sport, $startTime),
            'start_time'    => $startTime,
            'duration'      => (int) ($session['total_elapsed_time'] ?? $session['total_timer_time'] ?? 0),
            'distance'      => isset($session['total_distance']) ? (float) $session['total_distance'] : null,
            'elevation_gain'=> isset($session['total_ascent'])   ? (float) $session['total_ascent']   : null,
            'avg_hr'        => isset($session['avg_heart_rate']) ? (int)   $session['avg_heart_rate'] : null,
            'max_hr'        => isset($session['max_heart_rate']) ? (int)   $session['max_heart_rate'] : null,
            'calories'      => isset($session['total_calories']) ? (int)   $session['total_calories'] : null,
            'avg_speed'     => (float) ($session['avg_speed'] ?? $session['enhanced_avg_speed'] ?? 0) ?: null,
            'max_speed'     => (float) ($session['max_speed'] ?? $session['enhanced_max_speed'] ?? 0) ?: null,
            'avg_cadence'   => isset($session['avg_cadence'])    ? (int)   $session['avg_cadence']    : null,
            'avg_power'     => isset($session['avg_power'])      ? (int)   $session['avg_power']      : null,
            'laps'          => $this->parseLaps($fit),
            'trackpoints'   => $this->parseTrackpoints($fit),
        ];
    }

    /**
     * Return raw parsed data for debugging.
     */
    public function debugDump(string $filePath): array {
        $fit     = new phpFITFileAnalysis($filePath, ['fix_data' => ['all'], 'units' => 'metric']);
        $session = $fit->data_mesgs['session'] ?? [];
        $records = $fit->data_mesgs['record']  ?? [];
        $tsList  = $records['timestamp'] ?? [];

        $firstTs     = is_array($tsList) ? ($tsList[0] ?? null) : $tsList;
        $firstRecord = [];
        if ($firstTs !== null) {
            foreach ($records as $field => $values) {
                if ($field === 'timestamp') {
                    $firstRecord['timestamp'] = $firstTs;
                    continue;
                }
                $firstRecord[$field] = is_array($values) ? ($values[$firstTs] ?? null) : $values;
            }
        }

        return [
            'sport_raw'     => $session['sport'] ?? null,
            'sport_string'  => $this->decodeEnum($fit, 'sport', $session['sport'] ?? null),
            'session'       => $session,
            'record_fields' => array_keys($records),
            'record_count'  => is_array($tsList) ? count($tsList) : ($tsList !== null ? 1 : 0),
            'first_record'  => $firstRecord,
            'lap_fields'    => array_keys($fit->data_mesgs['lap'] ?? []),
        ];
    }

    // -------------------------------------------------------------------------

    private function detectSport(phpFITFileAnalysis $fit, array $session): string {
        $sportVal  = $session['sport'] ?? null;
        $raw       = strtolower((string) $this->decodeEnum($fit, 'sport', $sportVal));
        $sub       = strtolower((string) $this->decodeEnum($fit, 'sub_sport', $session['sub_sport'] ?? null));
        // Some devices (e.g. Garmin) store a free-text sport name in the sport message
        $sportName = strtolower((string) ($fit->data_mesgs['sport']['name'] ?? ''));

        if (str_contains($raw, 'cycl') || str_contains($raw, 'bik') || str_contains($sub, 'cycl') || str_contains($sub, 'bik') || str_contains($sportName, 'cycl') || str_contains($sportName, 'bik')) {
            return 'cycling';
        }
        if (str_contains($raw, 'swim') || str_contains($sub, 'swim') || str_contains($sportName, 'swim')) {
            return 'swimming';
        }
        if (str_contains($raw, 'hik') || str_contains($sub, 'hik') || str_contains($sportName, 'hik')) {
            return 'hiking';
        }
        if (str_contains($raw, 'run') || str_contains($sub, 'run') || str_contains($sportName, 'run')) {
            return 'running';
        }
        if (str_contains($raw, 'walk') || str_contains($sub, 'walk') || str_contains($sportName, 'walk')) {
            return 'hiking';
        }
        if (str_contains($raw, 'ski') || str_contains($sub, 'ski') || str_contains($sportName, 'ski')) {
            return 'skiing';
        }
        if (str_contains($raw, 'yoga') || str_contains($sub, 'yoga') || str_contains($sportName, 'yoga') ||
            str_contains($raw, 'breath') || str_contains($sub, 'breath') || str_contains($sportName, 'breath')) {
            return 'breathwork';
        }
        if (str_contains($raw, 'meditat') || str_contains($sub, 'meditat') || str_contains($sportName, 'meditat')) {
            return 'meditation';
        }
        if (str_contains($raw, 'train') || str_contains($raw, 'fitness') || str_contains($raw, 'strength') || str_contains($raw, 'gym') ||
            str_contains($sportName, 'train') || str_contains($sportName, 'fitness') || str_contains($sportName, 'strength') || str_contains($sportName, 'gym')) {
            return 'gym';
        }

        // Fall back: GPS present → assume running
        $records = $fit->data_mesgs['record'] ?? [];
        if (!empty($records['position_lat']) && !empty($records['position_long'])) {
            return 'running';
        }

        return 'gym';
    }

    private function decodeEnum(phpFITFileAnalysis $fit, string $type, mixed $value): string {
        if ($value === null) {
            return 'unknown';
        }
        return (string) $fit->enumData($type, $value);
    }

    private function resolveStartTime(phpFITFileAnalysis $fit, array $session): string {
        $ts = $session['start_time'] ?? null;

        if ($ts === null) {
            $tsList = $fit->data_mesgs['record']['timestamp'] ?? [];
            $ts     = is_array($tsList) ? ($tsList[0] ?? null) : $tsList;
        }

        if ($ts === null) {
            return (new \DateTime())->format('Y-m-d H:i:s');
        }

        // Timestamps are already Unix epoch (library adds FIT_UNIX_TS_DIFF by default)
        if (is_int($ts) || ctype_digit((string) $ts)) {
            return (new \DateTime('@' . (int) $ts))->format('Y-m-d H:i:s');
        }

        return (string) $ts;
    }

    private function buildName(string $sport, string $startTime): string {
        $label = ucfirst($sport);
        try {
            $dt = new \DateTime($startTime);
            return $label . ' – ' . $dt->format('d M Y');
        } catch (\Exception) {
            return $label . ' Activity';
        }
    }

    private function parseLaps(phpFITFileAnalysis $fit): array {
        $lapData = $fit->data_mesgs['lap'] ?? [];
        if (empty($lapData)) {
            return [];
        }

        $timestamps = $lapData['timestamp'] ?? [];
        // After oneElementArrays(), single-lap files have scalar timestamps
        if (!is_array($timestamps)) {
            $timestamps = [$timestamps];
        }
        $count = count($timestamps);

        $laps = [];
        for ($i = 0; $i < $count; $i++) {
            $getField = function (string $field) use ($lapData, $i): mixed {
                $v = $lapData[$field] ?? null;
                if ($v === null) {
                    return null;
                }
                return is_array($v) ? ($v[$i] ?? null) : $v;
            };

            $startTs = $getField('start_time');
            if ($startTs !== null && (is_int($startTs) || ctype_digit((string) $startTs))) {
                $startTs = (new \DateTime('@' . (int) $startTs))->format('Y-m-d H:i:s');
            }

            $dur  = $getField('total_elapsed_time') ?? $getField('total_timer_time');
            $dist = $getField('total_distance');
            $ahr  = $getField('avg_heart_rate');
            $mhr  = $getField('max_heart_rate');
            $spd  = $getField('avg_speed') ?? $getField('enhanced_avg_speed');
            // Last resort: derive avg speed from distance(km) / duration(s) → km/h
            if ($spd === null && $dist !== null && $dur !== null && $dur > 0) {
                $spd = (float) $dist / (float) $dur * 3600;
            }
            $asc  = $getField('total_ascent');
            $cal  = $getField('total_calories');

            $laps[] = [
                'lap_number'     => $i + 1,
                'start_time'     => $startTs,
                'duration'       => $dur  !== null ? (int)   $dur  : null,
                'distance'       => $dist !== null ? (float) $dist : null,
                'avg_hr'         => $ahr  !== null ? (int)   $ahr  : null,
                'max_hr'         => $mhr  !== null ? (int)   $mhr  : null,
                'avg_speed'      => $spd  !== null ? (float) $spd  : null,
                'elevation_gain' => $asc  !== null ? (float) $asc  : null,
                'calories'       => $cal  !== null ? (int)   $cal  : null,
            ];
        }

        return $laps;
    }

    private function parseTrackpoints(phpFITFileAnalysis $fit): array {
        $records    = $fit->data_mesgs['record'] ?? [];
        $timestamps = $records['timestamp'] ?? [];

        if (!is_array($timestamps)) {
            return [];
        }

        $trackpoints = [];
        foreach ($timestamps as $ts) {
            $get = function (string $field) use ($records, $ts): mixed {
                $v = $records[$field] ?? null;
                if ($v === null) {
                    return null;
                }
                return is_array($v) ? ($v[$ts] ?? null) : $v;
            };

            $trackpoints[] = [
                'timestamp'  => (new \DateTime('@' . (int) $ts))->format('Y-m-d H:i:s'),
                'lat'        => ($v = $get('position_lat'))   !== null ? (float) $v : null,
                'lon'        => ($v = $get('position_long'))  !== null ? (float) $v : null,
                'altitude'   => ($v = ($get('altitude') ?? $get('enhanced_altitude'))) !== null ? (float) $v : null,
                'distance'   => ($v = $get('distance'))       !== null ? (float) $v : null,
                'heart_rate' => ($v = $get('heart_rate'))     !== null ? (int)   $v : null,
                'speed'      => ($v = ($get('speed') ?? $get('enhanced_speed'))) !== null ? (float) $v : null,
                'cadence'    => ($v = $get('cadence'))        !== null ? (int)   $v : null,
                'power'      => ($v = $get('power'))          !== null ? (int)   $v : null,
            ];
        }

        return $trackpoints;
    }
}
