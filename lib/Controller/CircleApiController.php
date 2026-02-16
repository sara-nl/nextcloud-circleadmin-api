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
        $ownerUserId = $owner ?: $this->userId;
        try {
            return new DataResponse(
                $this->service->createCircle($name, $ownerUserId),
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
