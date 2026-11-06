<?php

declare(strict_types=1);

namespace OCA\FitTracker\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Public link to one activity. Keyed by file id like Meta, so the link keeps
 * working after the activity is re-imported.
 *
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getFileId()
 * @method void setFileId(int $fileId)
 * @method string getToken()
 * @method void setToken(string $token)
 * @method bool getHideEnds()
 * @method void setHideEnds(bool $hideEnds)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 */
class Share extends Entity {
    protected string $userId = '';
    protected int $fileId = 0;
    protected string $token = '';
    protected bool $hideEnds = true;
    protected string $createdAt = '';

    public function __construct() {
        $this->addType('fileId', 'integer');
        $this->addType('hideEnds', 'boolean');
    }

    public function setUserId(string $userId): void {
        $this->userId = $userId;
        $this->markFieldUpdated('userId');
    }

    public function setFileId(int $fileId): void {
        $this->fileId = $fileId;
        $this->markFieldUpdated('fileId');
    }

    public function setToken(string $token): void {
        $this->token = $token;
        $this->markFieldUpdated('token');
    }

    // Always written: the column default (true) is not what an explicit false means
    public function setHideEnds(bool $hideEnds): void {
        $this->hideEnds = $hideEnds;
        $this->markFieldUpdated('hideEnds');
    }

    public function setCreatedAt(string $createdAt): void {
        $this->createdAt = $createdAt;
        $this->markFieldUpdated('createdAt');
    }
}
