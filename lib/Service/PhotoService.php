<?php

declare(strict_types=1);

namespace OCA\FitTracker\Service;

use OC\Files\Search\SearchBinaryOperator;
use OC\Files\Search\SearchComparison;
use OC\Files\Search\SearchQuery;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\Search\ISearchBinaryOperator;
use OCP\Files\Search\ISearchComparison;

class PhotoService {

    private const PROXIMITY_METERS = 200;
    private const MAX_PHOTOS       = 50;

    public function __construct(
        private IRootFolder $rootFolder,
    ) {}

    /**
     * Find JPEG photos taken during an activity whose GPS coordinates lie on
     * the activity's GPS track. Photos without GPS EXIF data are ignored.
     *
     * @param array[] $trackpoints  Each element must have 'lat' and 'lon' keys (may be null).
     * @return array[]
     */
    public function getPhotosForActivity(
        string $userId,
        string $startTime,
        int    $duration,
        array  $trackpoints
    ): array {
        $gpsTrackpoints = array_values(array_filter(
            $trackpoints,
            fn($tp) => isset($tp['lat'], $tp['lon']) && $tp['lat'] !== null && $tp['lon'] !== null
        ));

        if (empty($gpsTrackpoints)) {
            return [];
        }

        try {
            $start = new \DateTime($startTime);
        } catch (\Exception) {
            return [];
        }
        $end = (clone $start)->modify("+{$duration} seconds");

        // Bounding box of the GPS track plus a small buffer (~200 m)
        $lats   = array_column($gpsTrackpoints, 'lat');
        $lons   = array_column($gpsTrackpoints, 'lon');
        $buf    = 0.002;
        $bounds = [
            'minLat' => min($lats) - $buf,
            'maxLat' => max($lats) + $buf,
            'minLon' => min($lons) - $buf,
            'maxLon' => max($lons) + $buf,
        ];

        try {
            $userFolder = $this->rootFolder->getUserFolder($userId);
        } catch (NotFoundException) {
            return [];
        }

        $startTs = $start->getTimestamp();
        $endTs   = $end->getTimestamp();
        // 26-hour buffer: 2 h activity slack + 24 h max UTC offset so EXIF local
        // timestamps (which carry no timezone) are never incorrectly rejected
        $windowBuffer = 26 * 3600;
        $minMtime     = $startTs - $windowBuffer;
        $maxMtime     = $endTs + $windowBuffer;

        // Push mime + mtime filter into DB so we never iterate all user jpegs.
        // COMPARE_GREATER_THAN with (x - 1) is equivalent to >= x for integer mtimes.
        $constraint = new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_AND, [
            new SearchComparison(ISearchComparison::COMPARE_EQUAL, 'mimetype', 'image/jpeg'),
            new SearchComparison(ISearchComparison::COMPARE_GREATER_THAN, 'mtime', $minMtime - 1),
            new SearchComparison(ISearchComparison::COMPARE_LESS_THAN, 'mtime', $maxMtime + 1),
        ]);
        $files  = $userFolder->search(new SearchQuery($constraint, 0, 0, []));
        $photos = [];

        foreach ($files as $file) {
            if (!($file instanceof File)) {
                continue;
            }
            if (count($photos) >= self::MAX_PHOTOS) {
                break;
            }

            $exif = $this->readExifHeader($file);
            if ($exif === null) {
                continue;
            }

            // Require GPS — photos without coordinates are ignored
            $coords = $this->extractGps($exif);
            if ($coords === null) {
                continue;
            }

            [$lat, $lon] = $coords;

            // Bounding box pre-filter (cheap) before running Haversine
            if ($lat < $bounds['minLat'] || $lat > $bounds['maxLat'] ||
                $lon < $bounds['minLon'] || $lon > $bounds['maxLon']) {
                continue;
            }

            if (!$this->isNearTrack($lat, $lon, $gpsTrackpoints)) {
                continue;
            }

            $photoTime = $this->extractTimestamp($exif);

            $photos[] = [
                'fileId'  => $file->getId(),
                'name'    => $file->getName(),
                'lat'     => $lat,
                'lon'     => $lon,
                'takenAt' => $photoTime?->format('Y-m-d H:i:s'),
            ];
        }

        return $photos;
    }

    private function readExifHeader(File $file): ?array {
        if (!function_exists('exif_read_data')) {
            return null;
        }

        try {
            // Stream only 64 KB — the JPEG APP1/EXIF segment is at most 65 534 B,
            // so GPS data is always within the first 64 KB.
            $handle = $file->fopen('rb');
            if (is_resource($handle)) {
                $header = (string) stream_get_contents($handle, 65536);
                fclose($handle);
            } else {
                // Storage doesn't support streaming; load full file then free ASAP.
                $content = $file->getContent();
                $header  = substr($content, 0, 65536);
                unset($content);
            }

            if (strlen($header) < 4 || substr($header, 0, 2) !== "\xFF\xD8") {
                return null;
            }

            $tmpPath = tempnam(sys_get_temp_dir(), 'fit_exif_');
            file_put_contents($tmpPath, $header);
            // as_arrays=true → GPS always nested under $exif['GPS'], EXIF under $exif['EXIF']
            $exif = @exif_read_data($tmpPath, null, true);
            @unlink($tmpPath);

            return $exif ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{float, float}|null  [lat, lon] */
    private function extractGps(array $exif): ?array {
        // GPS is always a nested sub-array in exif_read_data output
        $gps = $exif['GPS'] ?? [];

        if (!isset($gps['GPSLatitude'], $gps['GPSLatitudeRef'],
                    $gps['GPSLongitude'], $gps['GPSLongitudeRef'])) {
            return null;
        }

        $lat = $this->rationalsToDegrees($gps['GPSLatitude'], $gps['GPSLatitudeRef']);
        $lon = $this->rationalsToDegrees($gps['GPSLongitude'], $gps['GPSLongitudeRef']);

        return ($lat !== null && $lon !== null) ? [$lat, $lon] : null;
    }

    /** Convert EXIF rational array ["47/1", "30/1", "1234/100"] + hemisphere ref to decimal degrees. */
    private function rationalsToDegrees(array $parts, string $ref): ?float {
        if (count($parts) !== 3) {
            return null;
        }
        $deg = $this->rational($parts[0]);
        $min = $this->rational($parts[1]);
        $sec = $this->rational($parts[2]);

        if ($deg === null || $min === null || $sec === null) {
            return null;
        }

        $decimal = $deg + $min / 60.0 + $sec / 3600.0;
        return ($ref === 'S' || $ref === 'W') ? -$decimal : $decimal;
    }

    private function rational(string $value): ?float {
        if (!str_contains($value, '/')) {
            return (float)$value;
        }
        [$num, $den] = explode('/', $value, 2);
        return ((float)$den === 0.0) ? null : (float)$num / (float)$den;
    }

    private function extractTimestamp(array $exif): ?\DateTime {
        $raw = $exif['EXIF']['DateTimeOriginal']
            ?? $exif['EXIF']['DateTimeDigitized']
            ?? $exif['IFD0']['DateTime']
            ?? null;
        if ($raw === null || !is_string($raw)) {
            return null;
        }
        $dt = \DateTime::createFromFormat('Y:m:d H:i:s', $raw);
        return $dt ?: null;
    }

    private function isNearTrack(float $lat, float $lon, array $trackpoints): bool {
        foreach ($trackpoints as $tp) {
            if ($this->haversine($lat, $lon, (float)$tp['lat'], (float)$tp['lon']) <= self::PROXIMITY_METERS) {
                return true;
            }
        }
        return false;
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float {
        $R    = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2.0 * atan2(sqrt($a), sqrt(1.0 - $a));
    }
}
