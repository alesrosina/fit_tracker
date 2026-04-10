<?php

declare(strict_types=1);

namespace OCA\FitTracker\Controller;

use OCA\FitTracker\AppInfo\Application;
use OCA\FitTracker\Service\ActivityService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class ActivityController extends Controller {

    public function __construct(
        IRequest                   $request,
        private ActivityService    $activityService,
        private IUserSession       $userSession,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    public function index(): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }
        $syncErrors = [];
        try {
            $stats      = $this->activityService->syncFolder($userId);
            $syncErrors = $stats['errors'] ?? [];
        } catch (\Throwable $e) {
            $syncErrors = [get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()];
        }
        return new DataResponse([
            'activities' => $this->activityService->listForUser($userId),
            'syncErrors' => $syncErrors,
        ]);
    }

    #[NoAdminRequired]
    public function show(int $id): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }
        try {
            return new DataResponse($this->activityService->getForUser($id, $userId));
        } catch (DoesNotExistException) {
            return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
        }
    }

    #[NoAdminRequired]
    public function laps(int $id): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }
        try {
            return new DataResponse($this->activityService->getLapsForUser($id, $userId));
        } catch (DoesNotExistException) {
            return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
        }
    }

    #[NoAdminRequired]
    public function trackpoints(int $id): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }
        try {
            return new DataResponse($this->activityService->getTrackpointsForUser($id, $userId));
        } catch (DoesNotExistException) {
            return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
        }
    }

    #[NoAdminRequired]
    public function destroy(int $id): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }
        try {
            $this->activityService->deleteForUser($id, $userId);
            return new DataResponse([], Http::STATUS_NO_CONTENT);
        } catch (DoesNotExistException) {
            return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
        }
    }

    private function getUserId(): ?string {
        $user = $this->userSession->getUser();
        return $user?->getUID();
    }
}
