<?php

declare(strict_types=1);

namespace OCA\FitTracker\Service;

use Exception;

/**
 * Parses Garmin sleep FIT files (not supported by phpFITFileAnalysis).
 *
 * Key FIT global message numbers used by Garmin sleep files:
 *   275 (0x0113) – sleep_level:       field 253=timestamp, field 0=stage (0=unmeasurable,1=awake,2=light,3=deep,4=rem)
 *   346 (0x015A) – sleep_assessment:  field 6=overall_score (0-100)
 *   521 (0x0209) – sleep_stats:       field 1=hrv_score
 *
 * Detection: a FIT file is a sleep file if it contains a definition for global message 275.
 */
class SleepParserService {

    private const SLEEP_LEVEL_MSG      = 275;
    private const SLEEP_ASSESSMENT_MSG = 346;
    private const SLEEP_STATS_MSG      = 521;
    private const FIT_EPOCH            = 631065600; // seconds between 1970-01-01 and 1989-12-31

    private const STAGE_MAP = [
        0 => 'unmeasurable',
        1 => 'awake',
        2 => 'light',
        3 => 'deep',
        4 => 'rem',
    ];

    /**
     * Check whether a FIT file is a sleep file (contains sleep_level messages).
     */
    public function isSleepFile(string $filePath): bool {
        $messages = $this->parseBinary($filePath);
        return isset($messages[self::SLEEP_LEVEL_MSG]) && count($messages[self::SLEEP_LEVEL_MSG]) > 0;
    }

    /**
     * Parse a sleep FIT file and return structured sleep data.
     *
     * @return array{
     *   name: string,
     *   start_time: string,
     *   end_time: string,
     *   duration: int,
     *   score: ?int,
     *   hrv_score: ?int,
     *   time_deep: int,
     *   time_light: int,
     *   time_rem: int,
     *   time_awake: int,
     *   stages: array,
     * }
     */
    public function parse(string $filePath): array {
        if (!file_exists($filePath)) {
            throw new Exception("FIT file not found: $filePath");
        }

        $messages = $this->parseBinary($filePath);

        // ── Sleep stages ──────────────────────────────────────────────────────
        $stageMsgs = $messages[self::SLEEP_LEVEL_MSG] ?? [];
        $stages    = [];
        foreach ($stageMsgs as $msg) {
            $tsRaw    = $msg[253] ?? null;
            $stageRaw = $msg[0]   ?? null;
            if ($tsRaw === null || $stageRaw === null) {
                continue;
            }
            $stages[] = [
                'timestamp' => $this->fitTimestamp($tsRaw),
                'stage'     => self::STAGE_MAP[$stageRaw] ?? 'unmeasurable',
            ];
        }

        // ── Stage duration totals ─────────────────────────────────────────────
        $stageTotals = ['awake' => 0, 'light' => 0, 'deep' => 0, 'rem' => 0, 'unmeasurable' => 0];
        for ($i = 0; $i + 1 < count($stages); $i++) {
            $t1  = strtotime($stages[$i]['timestamp']);
            $t2  = strtotime($stages[$i + 1]['timestamp']);
            $dur = $t2 - $t1;
            if ($dur > 0 && $dur < 14400) { // sanity: max 4 h per single segment
                $stageTotals[$stages[$i]['stage']] += $dur;
            }
        }

        // ── Overall sleep score (global 346, field 6) ─────────────────────────
        $assessMsgs = $messages[self::SLEEP_ASSESSMENT_MSG] ?? [];
        $score      = null;
        if (!empty($assessMsgs)) {
            $raw = $assessMsgs[0][6] ?? null;
            if ($raw !== null && $raw !== 0xFF) {
                $score = (int) $raw;
            }
        }

        // ── HRV score (global 521, field 1) ───────────────────────────────────
        $statsMsgs = $messages[self::SLEEP_STATS_MSG] ?? [];
        $hrvScore  = null;
        if (!empty($statsMsgs)) {
            $raw = $statsMsgs[0][1] ?? null;
            if ($raw !== null && $raw !== 0xFF) {
                $hrvScore = (int) $raw;
            }
        }

        // ── Start / end times from stage records ──────────────────────────────
        $firstRaw = $stageMsgs[0][253]                        ?? null;
        $lastRaw  = $stageMsgs[count($stageMsgs) - 1][253]   ?? null;

        $startTime = $firstRaw !== null ? $this->fitTimestamp($firstRaw) : (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
        $endTime   = $lastRaw  !== null ? $this->fitTimestamp($lastRaw)  : $startTime;
        $duration  = ($firstRaw !== null && $lastRaw !== null) ? ($lastRaw - $firstRaw) : 0;

        // ── Build name ────────────────────────────────────────────────────────
        try {
            $dt   = new \DateTime($startTime);
            $name = 'Sleep – ' . $dt->format('d M Y');
        } catch (\Exception) {
            $name = 'Sleep';
        }

        return [
            'name'       => $name,
            'start_time' => $startTime,
            'end_time'   => $endTime,
            'duration'   => $duration,
            'score'      => $score,
            'hrv_score'  => $hrvScore,
            'time_deep'  => $stageTotals['deep'],
            'time_light' => $stageTotals['light'],
            'time_rem'   => $stageTotals['rem'],
            'time_awake' => $stageTotals['awake'],
            'stages'     => $stages,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal binary parser
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Parse a FIT file and return data records for relevant global message types.
     * Returns: [ globalMsgNum => [ [fieldDefNum => value, ...], ... ], ... ]
     */
    private function parseBinary(string $filePath): array {
        $raw = @file_get_contents($filePath);
        if ($raw === false || strlen($raw) < 14) {
            return [];
        }

        $len = strlen($raw);
        $pos = ord($raw[0]); // FIT header size (12 or 14)

        $definitions = []; // local_type => ['global', 'fields' => [['def','size']], 'recordSize', 'isLE']
        $messages    = []; // globalMsgNum => [[defNum => value, ...], ...]

        $interesting = [self::SLEEP_LEVEL_MSG, self::SLEEP_ASSESSMENT_MSG, self::SLEEP_STATS_MSG];

        while ($pos < $len - 2) {
            $hdr = ord($raw[$pos]);

            // ── Compressed timestamp record ───────────────────────────────────
            if (($hdr & 0x80) !== 0) {
                $localType = ($hdr >> 5) & 0x03;
                $pos++;
                if (isset($definitions[$localType])) {
                    $pos += $definitions[$localType]['recordSize'];
                }
                continue;
            }

            $isDef     = ($hdr & 0x40) !== 0;
            $hasDev    = ($hdr & 0x20) !== 0;
            $localType = $hdr & 0x0F;

            if ($isDef) {
                // ── Definition message ────────────────────────────────────────
                if ($pos + 5 >= $len) {
                    break;
                }
                $isLE      = ord($raw[$pos + 2]) === 0;
                $g0        = ord($raw[$pos + 3]);
                $g1        = ord($raw[$pos + 4]);
                $globalNum = $isLE ? ($g0 | ($g1 << 8)) : (($g0 << 8) | $g1);
                $numFields = ord($raw[$pos + 5]);

                $fields     = [];
                $recordSize = 0;
                for ($f = 0; $f < $numFields; $f++) {
                    $foff = $pos + 6 + $f * 3;
                    if ($foff + 1 >= $len) {
                        break;
                    }
                    $defNum     = ord($raw[$foff]);
                    $size       = ord($raw[$foff + 1]);
                    $fields[]   = ['def' => $defNum, 'size' => $size];
                    $recordSize += $size;
                }

                $definitions[$localType] = [
                    'global'     => $globalNum,
                    'fields'     => $fields,
                    'recordSize' => $recordSize,
                    'isLE'       => $isLE,
                ];

                $pos += 6 + $numFields * 3;
                if ($hasDev) {
                    if ($pos >= $len) {
                        break;
                    }
                    $numDev = ord($raw[$pos]);
                    $pos   += 1 + $numDev * 3;
                }
            } else {
                // ── Data message ──────────────────────────────────────────────
                $pos++;
                if (!isset($definitions[$localType])) {
                    continue;
                }

                $defn       = $definitions[$localType];
                $globalNum  = $defn['global'];
                $recordSize = $defn['recordSize'];

                if ($pos + $recordSize > $len) {
                    break;
                }

                if (in_array($globalNum, $interesting, true)) {
                    $record = [];
                    $rpos   = $pos;
                    foreach ($defn['fields'] as $field) {
                        $val = $this->readUint(substr($raw, $rpos, $field['size']), $field['size'], $defn['isLE']);
                        if ($val !== $this->invalidValue($field['size'])) {
                            $record[$field['def']] = $val;
                        }
                        $rpos += $field['size'];
                    }
                    $messages[$globalNum][] = $record;
                }

                $pos += $recordSize;
            }
        }

        return $messages;
    }

    private function readUint(string $bytes, int $size, bool $isLE): int {
        return match ($size) {
            1 => ord($bytes[0]),
            2 => $isLE
                ? (ord($bytes[0]) | (ord($bytes[1]) << 8))
                : ((ord($bytes[0]) << 8) | ord($bytes[1])),
            4 => $isLE
                ? (ord($bytes[0]) | (ord($bytes[1]) << 8) | (ord($bytes[2]) << 16) | (ord($bytes[3]) << 24))
                : ((ord($bytes[0]) << 24) | (ord($bytes[1]) << 16) | (ord($bytes[2]) << 8) | ord($bytes[3])),
            default => 0,
        };
    }

    /** Returns the "invalid / null" sentinel for a given field size. */
    private function invalidValue(int $size): int {
        return match ($size) {
            1 => 0xFF,
            2 => 0xFFFF,
            4 => 0xFFFFFFFF,
            default => -1,
        };
    }

    private function fitTimestamp(int $raw): string {
        return (new \DateTime('@' . ($raw + self::FIT_EPOCH)))->format('Y-m-d\TH:i:s\Z');
    }
}
