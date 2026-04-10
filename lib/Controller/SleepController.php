<?php

declare(strict_types=1);

namespace OCA\FitTracker\Controller;

use OCA\FitTracker\AppInfo\Application;
use OCA\FitTracker\Service\SleepService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class SleepController extends Controller {

    public function __construct(
        IRequest             $request,
        private SleepService $sleepService,
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
            $this->sleepService->deleteForUser($id, $userId);
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
