<?php

declare(strict_types=1);

namespace OCA\FitTracker\Controller;

use OCA\FitTracker\AppInfo\Application;
use OCA\FitTracker\Service\ActivityService;
use OCA\FitTracker\Service\SleepService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IRequest;
use OCP\IUserSession;

class SleepController extends Controller {

    public function __construct(
        IRequest             $request,
        private SleepService $sleepService,
        private ActivityService $activityService,
        private IUserSession $userSession,
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
            $stats      = $this->sleepService->syncFolder($userId);
            $syncErrors = $stats['errors'] ?? [];
        } catch (\Throwable $e) {
            $syncErrors = [get_class($e) . ': ' . $e->getMessage()];
        }
        return new DataResponse([
            'sessions'   => $this->sleepService->listForUser($userId),
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
            return new DataResponse($this->sleepService->getForUser($id, $userId));
        } catch (DoesNotExistException) {
            return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
        }
    }

    #[NoAdminRequired]
    public function stages(int $id): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }
        try {
            return new DataResponse($this->sleepService->getStagesForUser($id, $userId));
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
            // Move the .fit file to the trash first, so the next sync doesn't
            // import the session again.
            $sleep = $this->sleepService->findForUser($id, $userId);
            $this->activityService->trashFitFile($userId, $sleep->getFitFilePath());
            $this->sleepService->deleteRows($sleep, $userId);
            return new DataResponse([], Http::STATUS_NO_CONTENT);
        } catch (DoesNotExistException) {
            return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
        } catch (NotPermittedException) {
            return new DataResponse(['error' => 'file_not_deletable'], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * Drop the session's imported data and import its .fit file again. The
     * file may now be detected as an activity instead.
     */
    #[NoAdminRequired]
    public function reimport(int $id): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }
        try {
            $sleep = $this->sleepService->findForUser($id, $userId);
            $relativePath = $sleep->getFitFilePath();
            $file = $this->activityService->getFitFile($userId, $relativePath);
            $this->sleepService->deleteRows($sleep, $userId);
            return new DataResponse($this->activityService->importAnyType($userId, $file, $relativePath));
        } catch (DoesNotExistException) {
            return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
        } catch (NotFoundException) {
            return new DataResponse(['error' => 'file_missing'], Http::STATUS_CONFLICT);
        } catch (\Throwable $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    private function getUserId(): ?string {
        $user = $this->userSession->getUser();
        return $user?->getUID();
    }
}
