<?php

declare(strict_types=1);

namespace OCA\FitTracker\Controller;

use OCA\FitTracker\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller {

    public function __construct(IRequest $request) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse {
        Util::addScript(Application::APP_ID, 'fit_tracker-fit_tracker-main');
        Util::addStyle(Application::APP_ID, 'fit_tracker-main');
        Util::addTranslations(Application::APP_ID);
        $response = new TemplateResponse(Application::APP_ID, 'index');
        $response->setContentSecurityPolicy(self::mapPolicy());
        return $response;
    }

    /**
     * Allows the OpenStreetMap tiles of the route maps and heatmap. Without
     * this the maps only work while another app (e.g. Photos) happens to
     * allow the tile server globally.
     */
    public static function mapPolicy(): ContentSecurityPolicy {
        $policy = new ContentSecurityPolicy();
        $policy->addAllowedImageDomain('https://*.tile.openstreetmap.org');
        return $policy;
    }
}
