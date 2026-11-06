<?php

declare(strict_types=1);

namespace OCA\FitTracker\Controller;

use OCA\FitTracker\AppInfo\Application;
use OCA\FitTracker\Service\ActivityService;
use OCA\FitTracker\Service\CalendarService;
use OCA\FitTracker\Service\FitParserService;
use OCA\FitTracker\Service\SettingsService;
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
        private SettingsService $settings,
        private CalendarService $calendarService,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    public function getConfig(): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }
        return new DataResponse($this->settings->all($userId));
    }

    #[NoAdminRequired]
    public function setConfig(): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }
        try {
            $this->settings->update($userId, $this->request->getParams());
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
        return new DataResponse($this->settings->all($userId));
    }

    /** Calendars the user can add events to, for the calendar setting. */
    #[NoAdminRequired]
    public function calendars(): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }
        return new DataResponse($this->calendarService->writableCalendars($userId));
    }

    /** Add every activity to the chosen calendar; returns how many were new. */
    #[NoAdminRequired]
    public function exportCalendar(): DataResponse {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }
        if (!$this->calendarService->isEnabled($userId)) {
            return new DataResponse(['error' => 'No calendar chosen'], Http::STATUS_BAD_REQUEST);
        }
        return new DataResponse(['added' => $this->activityService->addAllToCalendar($userId)]);
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
