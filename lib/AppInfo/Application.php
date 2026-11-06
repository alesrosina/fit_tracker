<?php

declare(strict_types=1);

namespace OCA\FitTracker\AppInfo;

use OCA\FitTracker\Dashboard\ActivityWidget;
use OCA\FitTracker\Listener\UserDeletedListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\User\Events\UserDeletedEvent;

class Application extends App implements IBootstrap {
    public const APP_ID = 'fit_tracker';

    public function __construct() {
        parent::__construct(self::APP_ID);
        require_once __DIR__ . '/../../vendor/autoload.php';
    }

    public function register(IRegistrationContext $context): void {
        // Services are auto-wired via DI container
        $context->registerDashboardWidget(ActivityWidget::class);
        $context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);
    }

    public function boot(IBootContext $context): void {
    }
}
