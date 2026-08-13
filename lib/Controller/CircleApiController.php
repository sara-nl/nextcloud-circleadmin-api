<?php

declare(strict_types=1);

namespace OCA\CirclesAdmin\Controller;

use OCA\CirclesAdmin\Service\CirclesAdminService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class CircleApiController extends OCSController {

    private CirclesAdminService $service;
    private LoggerInterface $logger;
    private string $userId;

    public function __construct(
        string $appName,
        IRequest $request,
        CirclesAdminService $service,
        LoggerInterface $logger,
        ?string $userId
    ) {
        parent::__construct($appName, $request);
        $this->service = $service;
        $this->logger = $logger;
        $this->userId = $userId ?? '';
    }

    /**
     * @AdminRequired
     * @NoCSRFRequired
     */
    public function index(): DataResponse {
        try {
            return new DataResponse($this->service->listAll());
        } catch (\Exception $e) {
            $this->logger->error('circlesadmin: list failed: ' . $e->getMessage(), ['exception' => $e]);
            return new DataResponse(
                ['message' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @AdminRequired
     * @NoCSRFRequired
     */
    public function show(string $circleId): DataResponse {
        try {
            return new DataResponse($this->service->getCircle($circleId));
        } catch (\Exception $e) {
            $this->logger->error('circlesadmin: show failed for ' . $circleId . ': ' . $e->getMessage(), ['exception' => $e]);
            return new DataResponse(
                ['message' => $e->getMessage()],
                Http::STATUS_NOT_FOUND
            );
        }
    }

    /**
     * @AdminRequired
     * @NoCSRFRequired
     */
    public function create(string $name, string $owner = ''): DataResponse {
        // Get extra options from request params (not method params to avoid Dispatcher issues)
        $params = $this->request->getParams();
        $description = isset($params["desc"]) ? (string)$params["desc"] : null;
        $federated = !empty($params["federated"]);
        $appManaged = !empty($params["appManaged"]);
        // Role of the given owner in an app-managed team: moderator (default),
        // admin or member. Ignored for regular teams.
        $role = isset($params["role"]) ? (string)$params["role"] : 'moderator';
        // Optional config flags to set on the new team (e.g. federated, visible,
        // open). Applied after creation.
        $known = ['visible', 'open', 'invite', 'request', 'friend', 'protected', 'local', 'federated', 'mountpoint'];
        $configFlags = [];
        foreach ($known as $flag) {
            if (array_key_exists($flag, $params)) {
                $configFlags[$flag] = filter_var($params[$flag], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool)$params[$flag];
            }
        }
        // For app-managed teams the app itself is the owner, so an owner in the
        // body is optional (it becomes the human manager). Otherwise default
        // the owner to the calling admin.
        $ownerUserId = $appManaged ? $owner : ($owner ?: $this->userId);
        try {
            return new DataResponse(
                $this->service->createCircle($name, $ownerUserId, $description, $federated, $appManaged, $role, $configFlags),
                Http::STATUS_CREATED
            );
        } catch (\Exception $e) {
            $this->logger->error('circlesadmin: create failed: ' . $e->getMessage(), ['exception' => $e]);
            return new DataResponse(
                ['message' => $e->getMessage()],
                Http::STATUS_BAD_REQUEST
            );
        }
    }

    /**
     * @AdminRequired
     * @NoCSRFRequired
     */
    public function update(string $circleId, ?string $name = null, ?string $description = null): DataResponse {
        if ($name === null && $description === null) {
            return new DataResponse(
                ['message' => 'Provide at least one of: name, description'],
                Http::STATUS_BAD_REQUEST
            );
        }
        try {
            return new DataResponse($this->service->updateCircle($circleId, $name, $description));
        } catch (\Exception $e) {
            $this->logger->error('circlesadmin: update failed for ' . $circleId . ': ' . $e->getMessage(), ['exception' => $e]);
            return new DataResponse(
                ['message' => $e->getMessage()],
                Http::STATUS_BAD_REQUEST
            );
        }
    }

    /**
     * Toggle config flags on a team. Send the flag name with a truthy/falsy
     * value, e.g. `federated=1`, `visible=1`, `open=0`. Enabling `federated`
     * lets federated users be added via the Contacts app.
     *
     * @AdminRequired
     * @NoCSRFRequired
     */
    public function config(string $circleId): DataResponse {
        $params = $this->request->getParams();
        $known = ['visible', 'open', 'invite', 'request', 'friend', 'protected', 'local', 'federated', 'mountpoint'];
        $flags = [];
        foreach ($known as $name) {
            if (array_key_exists($name, $params)) {
                $flags[$name] = filter_var($params[$name], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool)$params[$name];
            }
        }
        if (empty($flags)) {
            return new DataResponse(
                ['message' => 'Provide at least one config flag: ' . implode(', ', $known)],
                Http::STATUS_BAD_REQUEST
            );
        }
        try {
            return new DataResponse($this->service->setCircleConfig($circleId, $flags));
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error('circlesadmin: config update failed for ' . $circleId . ': ' . $e->getMessage(), ['exception' => $e]);
            return new DataResponse(
                ['message' => $e->getMessage()],
                Http::STATUS_BAD_REQUEST
            );
        }
    }

    /**
     * @AdminRequired
     * @NoCSRFRequired
     */
    public function destroy(string $circleId): DataResponse {
        try {
            $this->service->destroyCircle($circleId);
            return new DataResponse(['message' => 'Circle deleted']);
        } catch (\Exception $e) {
            $this->logger->error('circlesadmin: destroy failed for ' . $circleId . ': ' . $e->getMessage(), ['exception' => $e]);
            return new DataResponse(
                ['message' => $e->getMessage()],
                Http::STATUS_BAD_REQUEST
            );
        }
    }
}
