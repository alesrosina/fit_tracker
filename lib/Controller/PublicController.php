<?php

declare(strict_types=1);

namespace OCA\FitTracker\Controller;

use OCA\FitTracker\AppInfo\Application;
use OCA\FitTracker\Service\ShareService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\Util;

/** Read-only page behind a public activity link (/apps/fit_tracker/s/{token}). */
class PublicController extends Controller {
    public function __construct(
        IRequest $request,
        private ShareService $shareService,
        private IInitialState $initialState,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[PublicPage]
    #[NoCSRFRequired]
    #[BruteForceProtection(action: 'fit_tracker_share')]
    #[AnonRateLimit(limit: 60, period: 60)]
    public function show(string $token): TemplateResponse {
        $data = preg_match('/^[A-Za-z0-9]{24}$/', $token) ? $this->shareService->publicData($token) : null;
        if ($data === null) {
            $response = new TemplateResponse('core', '404', [], TemplateResponse::RENDER_AS_GUEST);
            $response->setStatus(Http::STATUS_NOT_FOUND);
            $response->throttle(['token' => $token]);
            return $response;
        }

        $this->initialState->provideInitialState('share', $data);
        Util::addScript(Application::APP_ID, 'fit_tracker-fit_tracker-public');
        Util::addStyle(Application::APP_ID, 'fit_tracker-public');
        Util::addTranslations(Application::APP_ID);

        $response = new PublicTemplateResponse(Application::APP_ID, 'public', []);
        $response->setHeaderTitle($data['activity']['name']);
        if ($data['owner'] !== '') {
            $response->setHeaderDetails($data['owner']);
        }
        $response->setContentSecurityPolicy(PageController::mapPolicy());
        return $response;
    }
}
