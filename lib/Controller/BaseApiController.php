<?php

declare(strict_types=1);

namespace OCA\FitTracker\Controller;

use OCA\FitTracker\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\NotFoundException;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Shared plumbing for the JSON controllers added in 1.3: the current user and
 * mapping of the usual exceptions to HTTP status codes.
 */
abstract class BaseApiController extends Controller {
    public function __construct(IRequest $request, private IUserSession $userSession) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Run $fn with the user id and wrap its result. Not found → 404, invalid
     * input → 400.
     */
    protected function handle(callable $fn, int $status = Http::STATUS_OK): DataResponse {
        $userId = $this->userSession->getUser()?->getUID();
        if ($userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }
        try {
            return new DataResponse($fn($userId), $status);
        } catch (DoesNotExistException) {
            return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (NotFoundException) {
            // The activity's .fit file is gone from the folder
            return new DataResponse(['error' => 'file_missing'], Http::STATUS_CONFLICT);
        }
    }

    /** Request parameters limited to the given keys (absent keys stay absent). */
    protected function params(array $keys): array {
        return array_intersect_key($this->request->getParams(), array_flip($keys));
    }
}
