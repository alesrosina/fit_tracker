<?php

declare(strict_types=1);

namespace OCA\FitTracker\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getStartTime()
 * @method void setStartTime(string $startTime)
 * @method string getEndTime()
 * @method void setEndTime(string $endTime)
 * @method int getDuration()
 * @method void setDuration(int $duration)
 * @method int|null getScore()
 * @method void setScore(?int $score)
 * @method int|null getHrvScore()
 * @method void setHrvScore(?int $hrvScore)
 * @method int|null getTimeDeep()
 * @method void setTimeDeep(?int $timeDeep)
 * @method int|null getTimeLight()
 * @method void setTimeLight(?int $timeLight)
 * @method int|null getTimeRem()
 * @method void setTimeRem(?int $timeRem)
 * @method int|null getTimeAwake()
 * @method void setTimeAwake(?int $timeAwake)
 * @method string getFitFilePath()
 * @method void setFitFilePath(string $fitFilePath)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 */
class Sleep extends Entity {
    protected string $userId = '';
    protected string $name = '';
    protected string $startTime = '';
    protected string $endTime = '';
    protected int $duration = 0;
    protected ?int $score = null;
    protected ?int $hrvScore = null;
    protected ?int $timeDeep = null;
    protected ?int $timeLight = null;
    protected ?int $timeRem = null;
    protected ?int $timeAwake = null;
    protected string $fitFilePath = '';
    protected string $createdAt = '';

    public function __construct() {
        $this->addType('duration', 'integer');
        $this->addType('score', 'integer');
        $this->addType('hrvScore', 'integer');
        $this->addType('timeDeep', 'integer');
        $this->addType('timeLight', 'integer');
        $this->addType('timeRem', 'integer');
        $this->addType('timeAwake', 'integer');
    }

    public function setUserId(string $userId): void {
        $this->userId = $userId;
        $this->markFieldUpdated('userId');
    }

    public function setName(string $name): void {
        $this->name = $name;
        $this->markFieldUpdated('name');
    }

    public function setStartTime(string $startTime): void {
        $this->startTime = $startTime;
        $this->markFieldUpdated('startTime');
    }

    public function setEndTime(string $endTime): void {
        $this->endTime = $endTime;
        $this->markFieldUpdated('endTime');
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
            'id'         => $this->getId(),
            'name'       => $this->name,
            'startTime'  => $this->startTime,
            'endTime'    => $this->endTime,
            'duration'   => $this->duration,
            'score'      => $this->score,
            'hrvScore'   => $this->hrvScore,
            'timeDeep'   => $this->timeDeep,
            'timeLight'  => $this->timeLight,
            'timeRem'    => $this->timeRem,
            'timeAwake'  => $this->timeAwake,
            'createdAt'  => $this->createdAt,
        ];
    }
}
