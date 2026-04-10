<?php

declare(strict_types=1);

namespace OCA\FitTracker\Controller;

use OCA\FitTracker\AppInfo\Application;
use OCA\FitTracker\Service\ActivityService;
use OCA\FitTracker\Service\FitParserService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class ConfigController extends Controller {

    public function __construct(
        IRequest               $request,
        private ActivityService $activityService,
        private FitParserService $fitParser,
        private IUserSession   $userSession,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    public function getConfig(): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }
        return new DataResponse(['folder_path' => $this->activityService->getFolderPath($userId)]);
    }

    #[NoAdminRequired]
    public function setConfig(): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }
        $path = trim((string) $this->request->getParam('folder_path', ''));
        $this->activityService->setFolderPath($userId, $path);
        return new DataResponse(['folder_path' => $path]);
    }

    #[NoAdminRequired]
    public function debugFit(): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }
        try {
            $dump = $this->activityService->debugFirstFile($userId, $this->fitParser);
            return new DataResponse($dump);
        } catch (\Throwable $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    private function getUserId(): ?string {
        return $this->userSession->getUser()?->getUID();
    }
}
