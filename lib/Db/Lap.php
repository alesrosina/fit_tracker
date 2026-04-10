<?php

declare(strict_types=1);

namespace OCA\FitTracker\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getActivityId()
 * @method void setActivityId(int $activityId)
 * @method int getLapNumber()
 * @method void setLapNumber(int $lapNumber)
 * @method string|null getStartTime()
 * @method void setStartTime(?string $startTime)
 * @method int|null getDuration()
 * @method void setDuration(?int $duration)
 * @method float|null getDistance()
 * @method void setDistance(?float $distance)
 * @method int|null getAvgHr()
 * @method void setAvgHr(?int $avgHr)
 * @method int|null getMaxHr()
 * @method void setMaxHr(?int $maxHr)
 * @method float|null getAvgSpeed()
 * @method void setAvgSpeed(?float $avgSpeed)
 * @method float|null getElevationGain()
 * @method void setElevationGain(?float $elevationGain)
 * @method int|null getCalories()
 * @method void setCalories(?int $calories)
 */
class Lap extends Entity {
    protected int $activityId = 0;
    protected int $lapNumber = 0;
    protected ?string $startTime = null;
    protected ?int $duration = null;
    protected ?float $distance = null;
    protected ?int $avgHr = null;
    protected ?int $maxHr = null;
    protected ?float $avgSpeed = null;
    protected ?float $elevationGain = null;
    protected ?int $calories = null;

    public function __construct() {
        $this->addType('activityId', 'integer');
        $this->addType('lapNumber', 'integer');
        $this->addType('duration', 'integer');
        $this->addType('distance', 'float');
        $this->addType('avgHr', 'integer');
        $this->addType('maxHr', 'integer');
        $this->addType('avgSpeed', 'float');
        $this->addType('elevationGain', 'float');
        $this->addType('calories', 'integer');
    }

    public function toArray(): array {
        return [
            'id'            => $this->getId(),
            'activityId'    => $this->activityId,
            'lapNumber'     => $this->lapNumber,
            'startTime'     => $this->startTime,
            'duration'      => $this->duration,
            'distance'      => $this->distance,
            'avgHr'         => $this->avgHr,
            'maxHr'         => $this->maxHr,
            'avgSpeed'      => $this->avgSpeed,
            'elevationGain' => $this->elevationGain,
            'calories'      => $this->calories,
        ];
    }
}
