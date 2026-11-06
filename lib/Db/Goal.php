<?php

declare(strict_types=1);

namespace OCA\FitTracker\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string|null getSport()
 * @method void setSport(?string $sport)
 * @method string getMetric()
 * @method void setMetric(string $metric)
 * @method string getPeriod()
 * @method void setPeriod(string $period)
 * @method float getTarget()
 * @method void setTarget(float $target)
 * @method float|null getThreshold()
 * @method void setThreshold(?float $threshold)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 */
class Goal extends Entity {
    /**
     * Targets are stored in fixed units, independent of the unit setting:
     * km, hours, activities, m, days, kcal, TRIMP, hours per night, score
     * points, nights.
     */
    public const METRICS = [
        'distance', 'duration', 'count', 'elevation', 'active_days', 'calories', 'load',
        'sleep_hours', 'sleep_score', 'sleep_nights',
    ];
    public const SLEEP_METRICS = ['sleep_hours', 'sleep_score', 'sleep_nights'];
    public const PERIODS = ['week', 'month', 'year'];

    protected string $userId = '';
    protected ?string $sport = null;
    protected string $metric = '';
    protected string $period = '';
    protected float $target = 0;
    protected ?float $threshold = null;
    protected string $createdAt = '';

    public function __construct() {
        $this->addType('target', 'float');
        $this->addType('threshold', 'float');
    }

    public function setUserId(string $userId): void {
        $this->userId = $userId;
        $this->markFieldUpdated('userId');
    }

    public function setCreatedAt(string $createdAt): void {
        $this->createdAt = $createdAt;
        $this->markFieldUpdated('createdAt');
    }

    public function toArray(): array {
        return [
            'id'     => $this->getId(),
            'sport'  => $this->sport,
            'metric' => $this->metric,
            'period' => $this->period,
            'target' => $this->target,
            'threshold' => $this->threshold,
        ];
    }
}
