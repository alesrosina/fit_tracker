<?php

declare(strict_types=1);

namespace OCA\FitTracker\Controller;

use OCA\FitTracker\Service\ActivityService;
use OCA\FitTracker\Service\ShareService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

/** Managing public links; the public page itself is PublicController. */
class ShareController extends BaseApiController {
    public function __construct(
        IRequest $request,
        IUserSession $userSession,
        private ShareService $shareService,
        private ActivityService $activityService,
    ) {
        parent::__construct($request, $userSession);
    }

    #[NoAdminRequired]
    public function index(): DataResponse {
        return $this->handle(fn($uid) => $this->shareService->listForUser($uid));
    }

    #[NoAdminRequired]
    public function forActivity(int $id): DataResponse {
        return $this->handle(fn($uid) => $this->shareService->listForActivity($this->activityService->findEntityForUser($id, $uid), $uid));
    }

    #[NoAdminRequired]
    public function create(int $id, bool $hideEnds = true): DataResponse {
        return $this->handle(function ($uid) use ($id, $hideEnds) {
            return $this->shareService->create($this->activityService->findWithFileId($id, $uid), $uid, $hideEnds);
        }, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function destroy(int $id): DataResponse {
        return $this->handle(function ($uid) use ($id) {
            $this->shareService->delete($id, $uid);
            return [];
        });
    }
}
