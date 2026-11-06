<?php

declare(strict_types=1);

namespace OCA\FitTracker\Controller;

use OCA\FitTracker\Service\AnalysisService;
use OCA\FitTracker\Service\RoutePhotoService;
use OCA\FitTracker\Service\StatsService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class StatsController extends BaseApiController {
    public function __construct(
        IRequest $request,
        IUserSession $userSession,
        private StatsService $statsService,
        private AnalysisService $analysisService,
        private RoutePhotoService $routePhotoService,
    ) {
        parent::__construct($request, $userSession);
    }

    /** Analyze older activities for a few seconds; call again while remaining > 0. */
    #[NoAdminRequired]
    public function analyze(): DataResponse {
        return $this->handle(fn($uid) => $this->analysisService->analyzePending($uid));
    }

    #[NoAdminRequired]
    public function load(): DataResponse {
        return $this->handle(fn($uid) => $this->statsService->load($uid));
    }

    #[NoAdminRequired]
    public function records(): DataResponse {
        return $this->handle(fn($uid) => $this->statsService->records($uid));
    }

    /** Photos along the routes, in batches: call again with cursor = next until next is null. */
    #[NoAdminRequired]
    public function photos(?string $sport = null, int $cursor = 0): DataResponse {
        return $this->handle(fn($uid) => $this->routePhotoService->batch($uid, $sport ?: null, $cursor));
    }

    #[NoAdminRequired]
    public function heatmap(?string $sport = null): DataResponse {
        return $this->handle(fn($uid) => $this->statsService->heatmap($uid, $sport ?: null));
    }
}
