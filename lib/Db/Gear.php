<?php

declare(strict_types=1);

namespace OCA\FitTracker\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getType()
 * @method void setType(string $type)
 * @method string|null getDefaultSport()
 * @method void setDefaultSport(?string $defaultSport)
 * @method string|null getStartDate()
 * @method void setStartDate(?string $startDate)
 * @method float getInitialDistance()
 * @method void setInitialDistance(float $initialDistance)
 * @method float|null getRetireDistance()
 * @method void setRetireDistance(?float $retireDistance)
 * @method string|null getRetiredDate()
 * @method void setRetiredDate(?string $retiredDate)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 */
class Gear extends Entity {
    public const TYPES = ['shoes', 'bike', 'other'];

    protected string $userId = '';
    protected string $name = '';
    protected string $type = 'other';
    protected ?string $defaultSport = null;
    protected ?string $startDate = null;
    protected float $initialDistance = 0;
    protected ?float $retireDistance = null;
    protected ?string $retiredDate = null;
    protected string $createdAt = '';

    public function __construct() {
        $this->addType('initialDistance', 'float');
        $this->addType('retireDistance', 'float');
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
            'id'              => $this->getId(),
            'name'            => $this->name,
            'type'            => $this->type,
            'defaultSport'    => $this->defaultSport,
            'startDate'       => $this->startDate,
            'initialDistance' => $this->initialDistance,
            'retireDistance'  => $this->retireDistance,
            'retiredDate'     => $this->retiredDate,
        ];
    }
}
