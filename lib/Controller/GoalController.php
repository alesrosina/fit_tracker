<?php

declare(strict_types=1);

namespace OCA\FitTracker\Controller;

use OCA\FitTracker\Service\GoalService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class GoalController extends BaseApiController {
    private const FIELDS = ['sport', 'metric', 'period', 'target', 'threshold'];

    public function __construct(IRequest $request, IUserSession $userSession, private GoalService $goalService) {
        parent::__construct($request, $userSession);
    }

    #[NoAdminRequired]
    public function index(): DataResponse {
        return $this->handle(fn($uid) => $this->goalService->list($uid));
    }

    #[NoAdminRequired]
    public function create(): DataResponse {
        return $this->handle(fn($uid) => $this->goalService->create($uid, $this->params(self::FIELDS)), Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function update(int $id): DataResponse {
        return $this->handle(fn($uid) => $this->goalService->update($id, $uid, $this->params(self::FIELDS)));
    }

    #[NoAdminRequired]
    public function destroy(int $id): DataResponse {
        return $this->handle(function ($uid) use ($id) {
            $this->goalService->delete($id, $uid);
            return [];
        });
    }
}
