<?php

declare(strict_types=1);

namespace OCA\FitTracker\Controller;

use OCA\FitTracker\AppInfo\Application;
use OCA\FitTracker\Service\ActivityService;
use OCA\FitTracker\Service\PhotoService;
use OCA\FitTracker\Service\StatsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IRequest;
use OCP\IUserSession;

class ActivityController extends Controller {

    public function __construct(
        IRequest                   $request,
        private ActivityService    $activityService,
        private PhotoService       $photoService,
        private IUserSession       $userSession,
        private StatsService       $statsService,
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
            return new DataResponse($this->activityService->getForUser($id, $userId) + [
                'hrSettings' => $this->statsService->heartRateSettings($userId),
            ]);
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
    public function photos(int $id): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }
        try {
            $activity    = $this->activityService->getForUser($id, $userId);
            $trackpoints = $this->activityService->getTrackpointsForUser($id, $userId);

            $photos = $this->photoService->getPhotosForActivity(
                $userId,
                $activity['startTime'],
                $activity['duration'],
                $trackpoints
            );
            return new DataResponse($photos);
        } catch (DoesNotExistException) {
            return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
        }
    }

    #[NoAdminRequired]
    public function repairSport(): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }
        try {
            return new DataResponse($this->activityService->repairSportTypes($userId));
        } catch (\Throwable $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
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
        } catch (NotPermittedException) {
            return new DataResponse(['error' => 'file_not_deletable'], Http::STATUS_FORBIDDEN);
        }
    }

    #[NoAdminRequired]
    public function reimport(int $id): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }
        try {
            return new DataResponse($this->activityService->reimportForUser($id, $userId));
        } catch (DoesNotExistException) {
            return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
        } catch (NotFoundException) {
            return new DataResponse(['error' => 'file_missing'], Http::STATUS_CONFLICT);
        } catch (\Throwable $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    #[NoAdminRequired]
    public function reimportAll(): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }
        try {
            return new DataResponse($this->activityService->reimportAll($userId));
        } catch (\Throwable $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    #[NoAdminRequired]
    public function updateMeta(int $id): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }
        $fields = array_intersect_key($this->request->getParams(), array_flip(['name', 'notes', 'rpe', 'gearId']));
        try {
            return new DataResponse($this->activityService->updateMeta($id, $userId, $fields));
        } catch (DoesNotExistException) {
            return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
        } catch (NotFoundException) {
            return new DataResponse(['error' => 'file_missing'], Http::STATUS_CONFLICT);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }

    #[NoAdminRequired]
    public function weather(int $id): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }
        try {
            return new DataResponse(['weather' => $this->activityService->weather($id, $userId)]);
        } catch (DoesNotExistException) {
            return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
        } catch (NotFoundException) {
            return new DataResponse(['weather' => null]);
        } catch (\Throwable $e) {
            return new DataResponse(['error' => 'weather_unavailable'], Http::STATUS_BAD_GATEWAY);
        }
    }

    private function getUserId(): ?string {
        $user = $this->userSession->getUser();
        return $user?->getUID();
    }
}
