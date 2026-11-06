<?php

declare(strict_types=1);

namespace OCA\FitTracker\Db;

use OCP\AppFramework\Db\Entity;

/**
 * What the user adds to an activity by hand. Keyed by the .fit file's
 * Nextcloud file id, so it survives re-imports (see Version1200Date20261018000000).
 *
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getFileId()
 * @method void setFileId(int $fileId)
 * @method string|null getCustomName()
 * @method void setCustomName(?string $customName)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method int|null getRpe()
 * @method void setRpe(?int $rpe)
 * @method int|null getGearId()
 * @method void setGearId(?int $gearId)
 * @method string|null getWeather()
 * @method void setWeather(?string $weather)
 */
class Meta extends Entity {
    protected string $userId = '';
    protected int $fileId = 0;
    protected ?string $customName = null;
    protected ?string $notes = null;
    protected ?int $rpe = null;
    protected ?int $gearId = null;
    protected ?string $weather = null;

    public function __construct() {
        $this->addType('fileId', 'integer');
        $this->addType('rpe', 'integer');
        $this->addType('gearId', 'integer');
    }

    public function setUserId(string $userId): void {
        $this->userId = $userId;
        $this->markFieldUpdated('userId');
    }

    public function setFileId(int $fileId): void {
        $this->fileId = $fileId;
        $this->markFieldUpdated('fileId');
    }
}
