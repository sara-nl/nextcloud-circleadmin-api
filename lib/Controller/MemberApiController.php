<?php

declare(strict_types=1);

namespace OCA\CirclesAdmin\Controller;

use OCA\CirclesAdmin\Service\CirclesAdminService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class MemberApiController extends OCSController {

    private CirclesAdminService $service;
    private LoggerInterface $logger;

    public function __construct(
        string $appName,
        IRequest $request,
        CirclesAdminService $service,
        LoggerInterface $logger
    ) {
        parent::__construct($appName, $request);
        $this->service = $service;
        $this->logger = $logger;
    }

    /**
     * @AdminRequired
     * @NoCSRFRequired
     */
    public function index(string $circleId): DataResponse {
        try {
            return new DataResponse($this->service->getMembers($circleId));
        } catch (\Exception $e) {
            $this->logger->error('circlesadmin: members list failed for ' . $circleId . ': ' . $e->getMessage(), ['exception' => $e]);
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
    public function add(string $circleId, string $userId): DataResponse {
        try {
            return new DataResponse(
                $this->service->addMember($circleId, $userId),
                Http::STATUS_CREATED
            );
        } catch (\Exception $e) {
            $this->logger->error('circlesadmin: add member failed for ' . $circleId . '/' . $userId . ': ' . $e->getMessage(), ['exception' => $e]);
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
    public function remove(string $circleId, string $memberId): DataResponse {
        try {
            $this->service->removeMember($circleId, $memberId);
            return new DataResponse(['message' => 'Member removed']);
        } catch (\Exception $e) {
            $this->logger->error('circlesadmin: remove member failed: ' . $e->getMessage(), ['exception' => $e]);
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
    public function setLevel(string $circleId, string $memberId, int $level): DataResponse {
        try {
            $this->service->setMemberLevel($circleId, $memberId, $level);
            return new DataResponse(['message' => 'Level updated']);
        } catch (\Exception $e) {
            $this->logger->error('circlesadmin: set level failed: ' . $e->getMessage(), ['exception' => $e]);
            return new DataResponse(
                ['message' => $e->getMessage()],
                Http::STATUS_BAD_REQUEST
            );
        }
    }
}
