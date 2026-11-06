<?php

declare(strict_types=1);

namespace OCA\FitTracker\Service;

use OCP\Http\Client\IClientService;

/**
 * Weather at the start of an activity from Open-Meteo (free, no API key).
 * Only used when the user turned it on: it sends the start location, rounded
 * to about 1 km, and the date to open-meteo.com.
 */
class WeatherService {
    private const ARCHIVE_URL  = 'https://archive-api.open-meteo.com/v1/archive';
    private const FORECAST_URL = 'https://api.open-meteo.com/v1/forecast';
    // The archive lags a few days behind; newer dates come from the forecast API
    private const ARCHIVE_DELAY_DAYS = 6;
    private const HOURLY = 'temperature_2m,apparent_temperature,relative_humidity_2m,precipitation,wind_speed_10m,wind_direction_10m,weather_code';

    public function __construct(private IClientService $clientService) {}

    /**
     * @param string $startTimeUtc 'Y-m-d H:i:s' in UTC
     * @return array{temperature: ?float, apparentTemperature: ?float, humidity: ?float, precipitation: ?float, windSpeed: ?float, windDirection: ?float, weatherCode: ?int}|null
     *         null when Open-Meteo has no data for that time and place
     * @throws \Exception on network errors
     */
    public function fetch(float $lat, float $lon, string $startTimeUtc): ?array {
        $start = new \DateTimeImmutable($startTimeUtc, new \DateTimeZone('UTC'));
        $date  = $start->format('Y-m-d');
        $ageDays = (int) (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->diff($start)->days;

        $response = $this->clientService->newClient()->get(
            $ageDays > self::ARCHIVE_DELAY_DAYS ? self::ARCHIVE_URL : self::FORECAST_URL,
            [
                'query' => [
                    'latitude'        => round($lat, 2),
                    'longitude'       => round($lon, 2),
                    'start_date'      => $date,
                    'end_date'        => $date,
                    'hourly'          => self::HOURLY,
                    'timezone'        => 'UTC',
                    'wind_speed_unit' => 'kmh',
                ],
                'timeout' => 10,
            ],
        );
        $data = json_decode((string) $response->getBody(), true);
        $hourly = $data['hourly'] ?? null;
        if (!is_array($hourly) || empty($hourly['time'])) {
            return null;
        }

        // Hourly values; take the hour the activity started in
        $hour = (int) $start->format('G');
        $value = fn(string $key) => isset($hourly[$key][$hour]) ? $hourly[$key][$hour] : null;
        if ($value('temperature_2m') === null) {
            return null;
        }
        return [
            'temperature'         => $value('temperature_2m'),
            'apparentTemperature' => $value('apparent_temperature'),
            'humidity'            => $value('relative_humidity_2m'),
            'precipitation'       => $value('precipitation'),
            'windSpeed'           => $value('wind_speed_10m'),
            'windDirection'       => $value('wind_direction_10m'),
            'weatherCode'         => $value('weather_code') !== null ? (int) $value('weather_code') : null,
        ];
    }
}
