<?php

declare(strict_types=1);

namespace OCA\FitTracker\Controller;

use OCA\FitTracker\Db\ActivityMapper;
use OCA\FitTracker\Db\MetaMapper;
use OCA\FitTracker\Service\GearService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class GearController extends BaseApiController {
    private const FIELDS = ['name', 'type', 'defaultSport', 'startDate', 'retiredDate', 'initialDistance', 'retireDistance'];

    public function __construct(
        IRequest $request,
        IUserSession $userSession,
        private GearService $gearService,
        private ActivityMapper $activityMapper,
        private MetaMapper $metaMapper,
    ) {
        parent::__construct($request, $userSession);
    }

    /** All gear with distance, time and number of activities so far. */
    #[NoAdminRequired]
    public function index(): DataResponse {
        return $this->handle(fn($uid) => $this->gearService->listWithUsage(
            $uid,
            $this->activityMapper->findAllForUser($uid),
            $this->metaMapper->findAllForUser($uid),
        ));
    }

    #[NoAdminRequired]
    public function create(): DataResponse {
        return $this->handle(fn($uid) => $this->gearService->create($uid, $this->params(self::FIELDS))->toArray(), Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function update(int $id): DataResponse {
        return $this->handle(fn($uid) => $this->gearService->update($id, $uid, $this->params(self::FIELDS))->toArray());
    }

    #[NoAdminRequired]
    public function destroy(int $id): DataResponse {
        return $this->handle(function ($uid) use ($id) {
            $this->gearService->delete($id, $uid);
            return [];
        });
    }
}
