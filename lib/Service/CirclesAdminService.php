<?php

declare(strict_types=1);

namespace OCA\CirclesAdmin\Service;

use OCA\Circles\CirclesManager;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCA\Circles\Model\FederatedUser;
use OCA\Circles\Model\Probes\CircleProbe;
use OCA\Circles\Service\CircleService;
use OCP\IUserManager;
use OCP\Server;
use Psr\Log\LoggerInterface;

class CirclesAdminService {

    private CirclesManager $circlesManager;
    private IUserManager $userManager;
    private LoggerInterface $logger;

    public function __construct(
        CirclesManager $circlesManager,
        IUserManager $userManager,
        LoggerInterface $logger
    ) {
        $this->circlesManager = $circlesManager;
        $this->userManager = $userManager;
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

    public function createCircle(string $name, string $ownerUserId): array {
        $this->circlesManager->startSuperSession();
        $this->circlesManager->startAppSession('circlesadmin');
        try {
            $owner = $this->circlesManager->getFederatedUser($ownerUserId, Member::TYPE_USER);
            $circle = $this->circlesManager->createCircle($name, $owner);
            return $this->formatCircle($circle);
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
            // Return updated circle
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
        ];
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
