<?php

declare(strict_types=1);

namespace OCA\FitTracker\Service;

use OCA\FitTracker\AppInfo\Application;
use OCP\IConfig;

/**
 * Per-user settings, stored as Nextcloud user preferences. Numbers are null
 * when not set; callers fall back to defaults derived from the data (e.g. the
 * highest recorded heart rate for max HR).
 */
class SettingsService {
    public const DEFAULT_RESTING_HR = 60;
    public const DEFAULT_MAX_HR = 190;

    // key => [type, validator range or allowed values]
    private const SCHEMA = [
        'folder_path'     => ['string'],
        'units'           => ['enum', ['metric', 'imperial']],
        'max_hr'          => ['int', [100, 240]],
        'resting_hr'      => ['int', [25, 120]],
        'ftp'             => ['int', [50, 600]],
        'weather_enabled' => ['bool'],
        'calendar_uri'    => ['string'],
    ];

    public function __construct(private IConfig $config) {}

    public function all(string $userId): array {
        $out = [];
        foreach (array_keys(self::SCHEMA) as $key) {
            $out[$key] = $this->get($userId, $key);
        }
        return $out;
    }

    public function get(string $userId, string $key): mixed {
        [$type] = self::SCHEMA[$key];
        $raw = $this->config->getUserValue($userId, Application::APP_ID, $key, '');
        return match ($type) {
            'int'    => $raw === '' ? null : (int) $raw,
            'bool'   => $raw === '1',
            'enum'   => in_array($raw, self::SCHEMA[$key][1], true) ? $raw : self::SCHEMA[$key][1][0],
            default  => $raw,
        };
    }

    /**
     * Store the given settings; unknown keys are ignored. Throws on invalid
     * values so nothing half-valid is saved.
     *
     * @throws \InvalidArgumentException
     */
    public function update(string $userId, array $values): void {
        $toStore = [];
        foreach ($values as $key => $value) {
            if (!isset(self::SCHEMA[$key])) {
                continue;
            }
            $toStore[$key] = $this->normalize($key, $value);
        }
        foreach ($toStore as $key => $value) {
            $this->config->setUserValue($userId, Application::APP_ID, $key, $value);
        }
    }

    private function normalize(string $key, mixed $value): string {
        [$type] = self::SCHEMA[$key];
        switch ($type) {
            case 'int':
                if ($value === null || $value === '') {
                    return '';
                }
                [$min, $max] = self::SCHEMA[$key][1];
                if (!is_numeric($value) || (int) $value < $min || (int) $value > $max) {
                    throw new \InvalidArgumentException("$key must be between $min and $max");
                }
                return (string) (int) $value;
            case 'bool':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            case 'enum':
                if (!in_array($value, self::SCHEMA[$key][1], true)) {
                    throw new \InvalidArgumentException("Invalid value for $key");
                }
                return $value;
            default:
                return trim((string) $value);
        }
    }

    public function isImperial(string $userId): bool {
        return $this->get($userId, 'units') === 'imperial';
    }
}
