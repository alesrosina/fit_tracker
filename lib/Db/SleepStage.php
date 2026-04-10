<?php

declare(strict_types=1);

namespace OCA\FitTracker\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getSleepId()
 * @method void setSleepId(int $sleepId)
 * @method string getTimestamp()
 * @method void setTimestamp(string $timestamp)
 * @method string getStage()
 * @method void setStage(string $stage)
 */
class SleepStage extends Entity {
    protected int $sleepId = 0;
    protected string $timestamp = '';
    protected string $stage = '';

    public function __construct() {
        $this->addType('sleepId', 'integer');
    }

    public function toArray(): array {
        return [
            'timestamp' => $this->timestamp,
            'stage'     => $this->stage,
        ];
    }
}
