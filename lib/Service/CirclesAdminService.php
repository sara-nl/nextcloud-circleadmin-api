<?php

declare(strict_types=1);

namespace OCA\CirclesAdmin\Service;

use OCA\Circles\CirclesManager;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCA\Circles\Model\FederatedUser;
use OCA\Circles\Model\Probes\CircleProbe;
use OCA\Circles\Service\CircleService;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Server;
use Psr\Log\LoggerInterface;

class CirclesAdminService {

    private CirclesManager $circlesManager;
    private IUserManager $userManager;
    private IDBConnection $db;
    private LoggerInterface $logger;

    public function __construct(
        CirclesManager $circlesManager,
        IUserManager $userManager,
        IDBConnection $db,
        LoggerInterface $logger
    ) {
        $this->circlesManager = $circlesManager;
        $this->userManager = $userManager;
        $this->db = $db;
        $this->logger = $logger;
    }

    private function getCircleService(): CircleService {
        return Server::get(CircleService::class);
    }

    private function stopSession(): void {
        try {
            $this->circlesManager->stopSession();
        } catch (\Exception $e) {
        }
    }

    public function listAll(): array {
        $this->circlesManager->startSuperSession();
        try {
            $probe = new CircleProbe();
            $probe->includeSystemCircles()
                   ->includeSingleCircles()
                   ->includeHiddenCircles()
                   ->includeBackendCircles();
            $circles = $this->circlesManager->getCircles($probe);
            $result = [];
            foreach ($circles as $circle) {
                $result[] = $this->formatCircle($circle);
            }
            return $result;
        } finally {
            $this->stopSession();
        }
    }

    public function getCircle(string $circleId): array {
        $this->circlesManager->startSuperSession();
        try {
            $circle = $this->circlesManager->getCircle($circleId);
            $data = $this->formatCircle($circle);
            $data['description'] = $circle->getDescription();
            $data['members'] = [];
            foreach ($circle->getMembers() as $member) {
                $data['members'][] = $this->formatMember($member);
            }
            return $data;
        } finally {
            $this->stopSession();
        }
    }

    public function createCircle(string $name, string $ownerUserId, ?string $description = null): array {
        $this->circlesManager->startSuperSession();
        $this->circlesManager->startAppSession('circlesadmin');
        try {
            $owner = $this->circlesManager->getFederatedUser($ownerUserId, Member::TYPE_USER);
            $circle = $this->circlesManager->createCircle($name, $owner);
            $circleId = $circle->getSingleId();

            // Fix config: appSession creates with config=2 (personal), reset to 0 (open)
            // Also set description if provided
            $qb = $this->db->getQueryBuilder();
            $qb->update("circles_circle")
                ->set("config", $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
                ->where($qb->expr()->eq("unique_id", $qb->createNamedParameter($circleId)));
            if ($description !== null && $description !== "") {
                $qb->set("description", $qb->createNamedParameter($description));
            }
            $qb->executeStatement();

            $data = $this->formatCircle($circle);
            $data['description'] = $description ?? '';
            return $data;
        } finally {
            $this->stopSession();
        }
    }

    public function updateCircle(string $circleId, ?string $name, ?string $description): array {
        $this->circlesManager->startSuperSession(true);
        $this->circlesManager->startOccSession('', Member::TYPE_SINGLE, $circleId);
        try {
            $circleService = $this->getCircleService();
            if ($name !== null) {
                $circleService->updateName($circleId, $name);
            }
            if ($description !== null) {
                $circleService->updateDescription($circleId, $description);
            }
            $this->circlesManager->stopSession();
            $this->circlesManager->startSuperSession();
            $circle = $this->circlesManager->getCircle($circleId);
            $data = $this->formatCircle($circle);
            $data['description'] = $circle->getDescription();
            return $data;
        } finally {
            $this->stopSession();
        }
    }

    public function destroyCircle(string $circleId): void {
        $this->circlesManager->startSuperSession(true);
        $this->circlesManager->startOccSession('', Member::TYPE_SINGLE, $circleId);
        try {
            $this->circlesManager->destroyCircle($circleId);
        } finally {
            $this->stopSession();
        }
    }

    public function getMembers(string $circleId): array {
        $this->circlesManager->startSuperSession();
        try {
            $circle = $this->circlesManager->getCircle($circleId);
            $result = [];
            foreach ($circle->getMembers() as $member) {
                $result[] = $this->formatMember($member);
            }
            return $result;
        } finally {
            $this->stopSession();
        }
    }

    public function addMember(string $circleId, string $userId): array {
        $this->circlesManager->startSuperSession(true);
        $this->circlesManager->startOccSession('', Member::TYPE_SINGLE, $circleId);
        try {
            $federatedUser = $this->circlesManager->getFederatedUser($userId, Member::TYPE_USER);
            $member = $this->circlesManager->addMember($circleId, $federatedUser);
            return $this->formatMember($member);
        } finally {
            $this->stopSession();
        }
    }

    public function removeMember(string $circleId, string $memberId): void {
        $this->circlesManager->startSuperSession(true);
        $this->circlesManager->startOccSession('', Member::TYPE_SINGLE, $circleId);
        try {
            $this->circlesManager->removeMember($memberId);
        } finally {
            $this->stopSession();
        }
    }

    public function setMemberLevel(string $circleId, string $memberId, int $level): void {
        $this->circlesManager->startSuperSession(true);
        $this->circlesManager->startOccSession('', Member::TYPE_SINGLE, $circleId);
        try {
            $this->circlesManager->levelMember($memberId, $level);
        } finally {
            $this->stopSession();
        }
    }

    private function formatCircle(Circle $circle): array {
        $owner = $circle->getOwner();
        return [
            'id' => $circle->getSingleId(),
            'name' => $circle->getDisplayName(),
            'owner' => $owner ? $owner->getUserId() : null,
            'memberCount' => $circle->getMembers() ? count($circle->getMembers()) : 0,
            'config' => $circle->getConfig(),
            'source' => $circle->getSource(),
        ];
    }

    private function formatMember(Member $member): array {
        return [
            'id' => $member->getId(),
            'singleId' => $member->getSingleId(),
            'userId' => $member->getUserId(),
            'displayName' => $member->getDisplayName(),
            'level' => $member->getLevel(),
            'levelName' => $this->levelName($member->getLevel()),
            'status' => $member->getStatus(),
            'userType' => $member->getUserType(),
            'userTypeName' => $this->userTypeName($member->getUserType()),
        ];
    }

    private function userTypeName(int $type): string {
        return match ($type) {
            0 => 'Single',
            1 => 'User',
            2 => 'Group',
            4 => 'Mail',
            8 => 'Contact',
            16 => 'Circle',
            10000 => 'App',
            default => 'Unknown (' . $type . ')',
        };
    }

    private function levelName(int $level): string {
        return match ($level) {
            1 => 'Member',
            4 => 'Moderator',
            8 => 'Admin',
            9 => 'Owner',
            default => 'Unknown (' . $level . ')',
        };
    }
}
