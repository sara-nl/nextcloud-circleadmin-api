<?php

declare(strict_types=1);

namespace OCA\CirclesAdmin\Service;

use OCA\Circles\CirclesManager;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCA\Circles\Model\FederatedUser;
use OCA\Circles\Model\Probes\CircleProbe;
use OCA\Circles\Service\CircleService;
use OCA\Circles\Service\FederatedUserService;
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

    private function getFederatedUserService(): FederatedUserService {
        return Server::get(FederatedUserService::class);
    }

    /**
     * Probe that makes every circle visible to getCircle/getCircles, including
     * system, single, hidden, backend and app-managed circles. Needed because a
     * bare getCircle() does not return e.g. a freshly-created or app-managed
     * circle in the super/occ session, which otherwise throws CircleNotFound.
     */
    private function systemProbe(): CircleProbe {
        $probe = new CircleProbe();
        $probe->includeSystemCircles()
               ->includeSingleCircles()
               ->includeHiddenCircles()
               ->includeBackendCircles();
        return $probe;
    }

    private function roleLevel(string $role): int {
        return match (strtolower($role)) {
            'member' => Member::LEVEL_MEMBER,
            'admin' => Member::LEVEL_ADMIN,
            default => Member::LEVEL_MODERATOR,
        };
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
            $probe = new CircleProbe();
            $probe->includeSystemCircles()
                   ->includeSingleCircles()
                   ->includeHiddenCircles()
                   ->includeBackendCircles();
            $circle = $this->circlesManager->getCircle($circleId, $probe);
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

    public function createCircle(string $name, string $ownerUserId, ?string $description = null, bool $federated = false, bool $appManaged = false, string $role = 'moderator', array $configFlags = []): array {
        if ($appManaged) {
            return $this->createAppManagedCircle($name, $ownerUserId, $description, $role, $configFlags);
        }

        // Base config: local (CFG_LOCAL) unless federated is requested. Any other
        // requested flags are OR-ed in here in the same write, so we never have to
        // re-load the just-created circle in a second session (which is not yet
        // reliably visible and caused "Circle not found").
        $config = 4096;
        foreach ($configFlags as $flag => $enabled) {
            if ($enabled) {
                $config |= $this->configFlagBit((string)$flag);
            }
        }

        $this->circlesManager->startSuperSession();
        $this->circlesManager->startAppSession('circlesadmin');
        try {
            $owner = $this->circlesManager->getFederatedUser($ownerUserId, Member::TYPE_USER);
            $circle = $this->circlesManager->createCircle($name, $owner);
            $circleId = $circle->getSingleId();

            // Fix config: appSession creates with config=2 (personal); reset to the
            // computed config (local + any requested flags). Also set description.
            $qb = $this->db->getQueryBuilder();
            $qb->update("circles_circle")
                ->set("config", $qb->createNamedParameter($config, IQueryBuilder::PARAM_INT))
                ->where($qb->expr()->eq("unique_id", $qb->createNamedParameter($circleId)));
            if ($description !== null && $description !== "") {
                $qb->set("description", $qb->createNamedParameter($description));
            }
            $qb->executeStatement();

            $data = $this->formatCircle($circle);
            $data['description'] = $description ?? '';
            $data['config'] = $config;
            $data['configFlags'] = $this->configFlagNames($config);
            $data['federated'] = ($config & Circle::CFG_FEDERATED) !== 0;
            $data['appManaged'] = ($config & Circle::CFG_APP) !== 0;
            return $data;
        } finally {
            $this->stopSession();
        }
    }

    /**
     * Create a team owned by the Circles app itself and flagged as app-managed
     * (CFG_APP). Such a team cannot be edited, renamed or deleted from the
     * Nextcloud Teams UI by regular users; members are managed only through this
     * admin API. If a $managerUserId is given, that user is added at the level
     * matching $role ('moderator' by default) so they can manage members from
     * the UI without being able to change the team's settings. A 'moderator' or
     * 'member' can manage members but not edit/delete the team; an 'admin' also
     * gets the edit/settings UI. Pass an empty string for a team with no human
     * manager.
     */
    private function createAppManagedCircle(string $name, string $managerUserId, ?string $description, string $role = 'moderator', array $configFlags = []): array {
        // Bits for any requested flags, OR-ed into the config in the same write as
        // CFG_APP below, so we never re-load the circle in a separate session.
        $flagBits = 0;
        foreach ($configFlags as $flag => $enabled) {
            if ($enabled) {
                $flagBits |= $this->configFlagBit((string)$flag);
            }
        }

        $this->circlesManager->startSuperSession();
        // startAppSession sets a "current app" patron so member operations
        // (createCircle owner, addMember) have a valid invitedBy context.
        $this->circlesManager->startAppSession('circlesadmin');
        try {
            // Owner is the Circles app itself, not a user (like the group system circles).
            $appOwner = $this->getFederatedUserService()->getAppInitiator('circles', Member::APP_CIRCLES);
            $circle = $this->circlesManager->createCircle($name, $appOwner);
            $circleId = $circle->getSingleId();

            // Lock the team from the front-end: sets CFG_APP.
            $this->circlesManager->flagAsAppManaged($circleId, true);

            if (($description !== null && $description !== '') || $flagBits !== 0) {
                $qb = $this->db->getQueryBuilder();
                $qb->update('circles_circle');
                if ($flagBits !== 0) {
                    // Keep CFG_APP (just set by flagAsAppManaged) and OR in the flags.
                    $qb->set('config', $qb->createNamedParameter(
                        Circle::CFG_APP | $flagBits, IQueryBuilder::PARAM_INT
                    ));
                }
                if ($description !== null && $description !== '') {
                    $qb->set('description', $qb->createNamedParameter($description));
                }
                $qb->where($qb->expr()->eq('unique_id', $qb->createNamedParameter($circleId)));
                $qb->executeStatement();
            }

            if ($managerUserId !== '') {
                $manager = $this->circlesManager->getFederatedUser($managerUserId, Member::TYPE_USER);
                $member = $this->circlesManager->addMember($circleId, $manager);
                $level = $this->roleLevel($role);
                if ($level !== Member::LEVEL_MEMBER) {
                    $this->circlesManager->levelMember($member->getId(), $level);
                }
            }

            $probe = new CircleProbe();
            $probe->includeSystemCircles();
            $circle = $this->circlesManager->getCircle($circleId, $probe);
            $data = $this->formatCircle($circle);
            $data['description'] = $description ?? '';
            return $data;
        } finally {
            $this->stopSession();
        }
    }

    public function updateCircle(string $circleId, ?string $name, ?string $description): array {
        try {
            $this->circlesManager->startSuperSession(true);
            $this->circlesManager->startOccSession('', Member::TYPE_SINGLE, $circleId);
            $circleService = $this->getCircleService();
            if ($name !== null) {
                $circleService->updateName($circleId, $name);
            }
            if ($description !== null) {
                $circleService->updateDescription($circleId, $description);
            }
            $this->circlesManager->stopSession();
            $this->circlesManager->startSuperSession();
            $circle = $this->circlesManager->getCircle($circleId, $this->systemProbe());
            $data = $this->formatCircle($circle);
            $data['description'] = $circle->getDescription();
            return $data;
        } finally {
            $this->stopSession();
        }
    }

    /**
     * Toggle user-settable config flags on a team. $flags maps a flag name (see
     * self::CONFIG_FLAGS, e.g. 'federated', 'visible', 'open') to a bool: true to
     * enable, false to disable. Enabling 'federated' lets federated users be
     * added to the team through the Nextcloud Contacts app.
     *
     * @param array<string, bool> $flags
     * @throws \InvalidArgumentException on an unknown flag name
     */
    public function setCircleConfig(string $circleId, array $flags): array {
        $config = $this->readCircleConfig($circleId);
        foreach ($flags as $name => $enabled) {
            $bit = $this->configFlagBit($name);
            if ($enabled) {
                $config |= $bit;
            } else {
                $config &= ~$bit;
            }
        }

        try {
            $this->circlesManager->startSuperSession(true);
            $this->circlesManager->startOccSession('', Member::TYPE_SINGLE, $circleId);
            $circle = $this->circlesManager->getCircle($circleId, $this->systemProbe());
            $circle->setConfig($config);
            $this->circlesManager->updateConfig($circle);
            $this->circlesManager->stopSession();

            $this->circlesManager->startSuperSession();
            $circle = $this->circlesManager->getCircle($circleId, $this->systemProbe());
            $data = $this->formatCircle($circle);
            $data['description'] = $circle->getDescription();
            return $data;
        } finally {
            $this->stopSession();
        }
    }

    private function configFlagBit(string $name): int {
        $name = strtolower(trim($name));
        if (!isset(self::CONFIG_FLAGS[$name])) {
            throw new \InvalidArgumentException(
                'Unknown config flag: ' . $name . '. Allowed: ' . implode(', ', array_keys(self::CONFIG_FLAGS))
            );
        }
        return self::CONFIG_FLAGS[$name];
    }

    public function destroyCircle(string $circleId): void {
        // App-managed teams (CFG_APP) are owned by the Circles app and cannot be
        // destroyed through the normal OCS path ("Team is managed from an other
        // app"); clear the flag first, using an app session so the app owner is a
        // valid initiator. Read the config straight from the DB so the lookup is
        // independent of session visibility.
        if (($this->readCircleConfig($circleId) & Circle::CFG_APP) !== 0) {
            try {
                $this->circlesManager->startSuperSession(true);
                $this->circlesManager->startAppSession('circlesadmin');
                $this->circlesManager->flagAsAppManaged($circleId, false);
                $this->circlesManager->destroyCircle($circleId);
                return;
            } finally {
                $this->stopSession();
            }
        }

        // Regular circle: destroy as the circle owner.
        try {
            $this->circlesManager->startSuperSession(true);
            $this->circlesManager->startOccSession('', Member::TYPE_SINGLE, $circleId);
            $this->circlesManager->destroyCircle($circleId);
        } finally {
            $this->stopSession();
        }
    }

    private function readCircleConfig(string $circleId): int {
        $qb = $this->db->getQueryBuilder();
        $qb->select('config')
            ->from('circles_circle')
            ->where($qb->expr()->eq('unique_id', $qb->createNamedParameter($circleId)));
        $result = $qb->executeQuery();
        $config = $result->fetchOne();
        $result->closeCursor();
        return $config === false ? 0 : (int)$config;
    }

    public function getMembers(string $circleId): array {
        $this->circlesManager->startSuperSession();
        try {
            $probe = new CircleProbe();
            $probe->includeSystemCircles()
                   ->includeSingleCircles()
                   ->includeHiddenCircles()
                   ->includeBackendCircles();
            $circle = $this->circlesManager->getCircle($circleId, $probe);
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
        try {
            $this->circlesManager->startSuperSession(true);
            $this->circlesManager->startOccSession('', Member::TYPE_SINGLE, $circleId);
            $federatedUser = $this->circlesManager->getFederatedUser($userId, Member::TYPE_USER);
            $member = $this->circlesManager->addMember($circleId, $federatedUser);
            return $this->formatMember($member);
        } finally {
            $this->stopSession();
        }
    }

    public function removeMember(string $circleId, string $memberId): void {
        try {
            $this->circlesManager->startSuperSession(true);
            $this->circlesManager->startOccSession('', Member::TYPE_SINGLE, $circleId);
            $this->assertMemberInCircle($circleId, $memberId);
            $this->circlesManager->removeMember($memberId);
        } finally {
            $this->stopSession();
        }
    }

    public function setMemberLevel(string $circleId, string $memberId, int $level): void {
        if (!in_array($level, [Member::LEVEL_MEMBER, Member::LEVEL_MODERATOR, Member::LEVEL_ADMIN, Member::LEVEL_OWNER], true)) {
            throw new \InvalidArgumentException('Invalid level. Allowed: 1 (Member), 4 (Moderator), 8 (Admin), 9 (Owner).');
        }
        try {
            $this->circlesManager->startSuperSession(true);
            $this->circlesManager->startOccSession('', Member::TYPE_SINGLE, $circleId);
            $this->assertMemberInCircle($circleId, $memberId);
            $this->circlesManager->levelMember($memberId, $level);
        } finally {
            $this->stopSession();
        }
    }

    /**
     * Ensure the given member ID actually belongs to the given circle, so a
     * member of another circle cannot be mutated through this circle's URL.
     *
     * @throws \InvalidArgumentException if the member is not part of the circle
     */
    private function assertMemberInCircle(string $circleId, string $memberId): void {
        $circle = $this->circlesManager->getCircle($circleId, $this->systemProbe());
        foreach ($circle->getMembers() as $member) {
            if ($member->getId() === $memberId) {
                return;
            }
        }
        throw new \InvalidArgumentException('Member not found in this team.');
    }

    /**
     * User-settable config flags, keyed by the name accepted/returned by the API.
     * These are the flags an admin may toggle on a team; system flags
     * (SINGLE/PERSONAL/SYSTEM/NO_OWNER/HIDDEN/BACKEND) are managed internally by
     * Circles and are intentionally not exposed here.
     */
    private const CONFIG_FLAGS = [
        'visible' => Circle::CFG_VISIBLE,       // 8    - visible to everyone in search
        'open' => Circle::CFG_OPEN,             // 16   - anyone can join
        'invite' => Circle::CFG_INVITE,         // 32   - joining requires an invitation
        'request' => Circle::CFG_REQUEST,       // 64   - join request needs moderator approval
        'friend' => Circle::CFG_FRIEND,         // 128  - members can invite their friends
        'protected' => Circle::CFG_PROTECTED,   // 256  - password protected
        'local' => Circle::CFG_LOCAL,           // 4096 - local only (not federated)
        'federated' => Circle::CFG_FEDERATED,   // 32768- federated: add users from other instances
        'mountpoint' => Circle::CFG_MOUNTPOINT, // 65536- create a Files folder
    ];

    private function formatCircle(Circle $circle): array {
        $owner = $circle->getOwner();
        return [
            'id' => $circle->getSingleId(),
            'name' => $circle->getDisplayName(),
            'owner' => $owner ? $owner->getUserId() : null,
            'memberCount' => $circle->getMembers() ? count($circle->getMembers()) : 0,
            'config' => $circle->getConfig(),
            'configFlags' => $this->configFlagNames($circle->getConfig()),
            'appManaged' => ($circle->getConfig() & Circle::CFG_APP) !== 0,
            'federated' => ($circle->getConfig() & Circle::CFG_FEDERATED) !== 0,
            'source' => $circle->getSource(),
        ];
    }

    /**
     * Readable list of the user-settable flags currently set on a config value,
     * e.g. [8192 => …] config 40968 -> ['visible', 'federated'].
     */
    private function configFlagNames(int $config): array {
        $names = [];
        foreach (self::CONFIG_FLAGS as $name => $bit) {
            if (($config & $bit) !== 0) {
                $names[] = $name;
            }
        }
        return $names;
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
            'statusName' => $this->statusName($member),
            'userType' => $member->getUserType(),
            'userTypeName' => $this->userTypeName($member->getUserType()),
        ];
    }

    /**
     * Human-friendly member status. Circles reports members that have been
     * invited to a circle but not yet accepted with level 0 (no membership
     * level) and an empty/non-"Member" status, which reads as "unknown".
     * Surface that as "Invited" instead.
     */
    private function statusName(Member $member): string {
        $status = $member->getStatus();
        return match ($status) {
            Member::STATUS_MEMBER => 'Member',
            Member::STATUS_INVITED => 'Invited',
            Member::STATUS_REQUEST => 'Requesting',
            Member::STATUS_BLOCKED => 'Blocked',
            default => $member->getLevel() === Member::LEVEL_NONE ? 'Invited' : ($status ?: 'Unknown'),
        };
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
