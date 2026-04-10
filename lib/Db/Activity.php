<?php

declare(strict_types=1);

namespace OCA\FitTracker\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getSport()
 * @method void setSport(string $sport)
 * @method string getStartTime()
 * @method void setStartTime(string $startTime)
 * @method int getDuration()
 * @method void setDuration(int $duration)
 * @method float|null getDistance()
 * @method void setDistance(?float $distance)
 * @method float|null getElevationGain()
 * @method void setElevationGain(?float $elevationGain)
 * @method int|null getAvgHr()
 * @method void setAvgHr(?int $avgHr)
 * @method int|null getMaxHr()
 * @method void setMaxHr(?int $maxHr)
 * @method int|null getCalories()
 * @method void setCalories(?int $calories)
 * @method float|null getAvgSpeed()
 * @method void setAvgSpeed(?float $avgSpeed)
 * @method float|null getMaxSpeed()
 * @method void setMaxSpeed(?float $maxSpeed)
 * @method int|null getAvgCadence()
 * @method void setAvgCadence(?int $avgCadence)
 * @method int|null getAvgPower()
 * @method void setAvgPower(?int $avgPower)
 * @method string getFitFilePath()
 * @method void setFitFilePath(string $fitFilePath)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 */
class Activity extends Entity {
    protected string $userId = '';
    protected string $name = '';
    protected string $sport = '';
    protected string $startTime = '';
    protected int $duration = 0;
    protected ?float $distance = null;
    protected ?float $elevationGain = null;
    protected ?int $avgHr = null;
    protected ?int $maxHr = null;
    protected ?int $calories = null;
    protected ?float $avgSpeed = null;
    protected ?float $maxSpeed = null;
    protected ?int $avgCadence = null;
    protected ?int $avgPower = null;
    protected string $fitFilePath = '';
    protected string $createdAt = '';

    public function __construct() {
        $this->addType('duration', 'integer');
        $this->addType('distance', 'float');
        $this->addType('elevationGain', 'float');
        $this->addType('avgHr', 'integer');
        $this->addType('maxHr', 'integer');
        $this->addType('calories', 'integer');
        $this->addType('avgSpeed', 'float');
        $this->addType('maxSpeed', 'float');
        $this->addType('avgCadence', 'integer');
        $this->addType('avgPower', 'integer');
    }

    // Explicit setters for required fields to ensure they are always marked
    // as updated even when the value equals the property default.
    public function setUserId(string $userId): void {
        $this->userId = $userId;
        $this->markFieldUpdated('userId');
    }

    public function setName(string $name): void {
        $this->name = $name;
        $this->markFieldUpdated('name');
    }

    public function setSport(string $sport): void {
        $this->sport = $sport;
        $this->markFieldUpdated('sport');
    }

    public function setStartTime(string $startTime): void {
        $this->startTime = $startTime;
        $this->markFieldUpdated('startTime');
    }

    public function setDuration(int $duration): void {
        $this->duration = $duration;
        $this->markFieldUpdated('duration');
    }

    public function setFitFilePath(string $fitFilePath): void {
        $this->fitFilePath = $fitFilePath;
        $this->markFieldUpdated('fitFilePath');
    }

    public function setCreatedAt(string $createdAt): void {
        $this->createdAt = $createdAt;
        $this->markFieldUpdated('createdAt');
    }

    public function toArray(): array {
        return [
            'id'            => $this->getId(),
            'name'          => $this->name,
            'sport'         => $this->sport,
            'startTime'     => $this->startTime,
            'duration'      => $this->duration,
            'distance'      => $this->distance,
            'elevationGain' => $this->elevationGain,
            'avgHr'         => $this->avgHr,
            'maxHr'         => $this->maxHr,
            'calories'      => $this->calories,
            'avgSpeed'      => $this->avgSpeed,
            'maxSpeed'      => $this->maxSpeed,
            'avgCadence'    => $this->avgCadence,
            'avgPower'      => $this->avgPower,
            'createdAt'     => $this->createdAt,
        ];
    }
}
