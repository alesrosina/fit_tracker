<?php

declare(strict_types=1);

namespace OCA\FitTracker\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
    public const APP_ID = 'fit_tracker';

    public function __construct() {
        parent::__construct(self::APP_ID);
        require_once __DIR__ . '/../../vendor/autoload.php';
    }

    public function register(IRegistrationContext $context): void {
        // Services are auto-wired via DI container
    }

    public function boot(IBootContext $context): void {
    }
}
