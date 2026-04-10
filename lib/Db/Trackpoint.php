<?php

declare(strict_types=1);

namespace OCA\FitTracker\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getActivityId()
 * @method void setActivityId(int $activityId)
 * @method string getTimestamp()
 * @method void setTimestamp(string $timestamp)
 * @method float|null getLat()
 * @method void setLat(?float $lat)
 * @method float|null getLon()
 * @method void setLon(?float $lon)
 * @method float|null getAltitude()
 * @method void setAltitude(?float $altitude)
 * @method float|null getDistance()
 * @method void setDistance(?float $distance)
 * @method int|null getHeartRate()
 * @method void setHeartRate(?int $heartRate)
 * @method float|null getSpeed()
 * @method void setSpeed(?float $speed)
 * @method int|null getCadence()
 * @method void setCadence(?int $cadence)
 * @method int|null getPower()
 * @method void setPower(?int $power)
 */
class Trackpoint extends Entity {
    protected int $activityId = 0;
    protected string $timestamp = '';
    protected ?float $lat = null;
    protected ?float $lon = null;
    protected ?float $altitude = null;
    protected ?float $distance = null;
    protected ?int $heartRate = null;
    protected ?float $speed = null;
    protected ?int $cadence = null;
    protected ?int $power = null;

    public function __construct() {
        $this->addType('activityId', 'integer');
        $this->addType('lat', 'float');
        $this->addType('lon', 'float');
        $this->addType('altitude', 'float');
        $this->addType('distance', 'float');
        $this->addType('heartRate', 'integer');
        $this->addType('speed', 'float');
        $this->addType('cadence', 'integer');
        $this->addType('power', 'integer');
    }

    public function toArray(): array {
        return [
            'timestamp' => $this->timestamp,
            'lat'       => $this->lat,
            'lon'       => $this->lon,
            'altitude'  => $this->altitude,
            'distance'  => $this->distance,
            'heartRate' => $this->heartRate,
            'speed'     => $this->speed,
            'cadence'   => $this->cadence,
            'power'     => $this->power,
        ];
    }
}
