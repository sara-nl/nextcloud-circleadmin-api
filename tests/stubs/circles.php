<?php

declare(strict_types=1);

// Psalm stubs for the parts of the Circles app this app uses. Circles is not part
// of nextcloud/ocp, so psalm cannot see these classes otherwise. The signatures
// follow Circles 35; add a method here when lib/ starts using it.

namespace OCA\Circles {

	use OCA\Circles\Model\Circle;
	use OCA\Circles\Model\FederatedUser;
	use OCA\Circles\Model\Member;
	use OCA\Circles\Model\Probes\CircleProbe;

	class CirclesManager {
		public function startSuperSession(bool $forceSync = false): void {
		}

		public function startOccSession(string $userId, int $userType = Member::TYPE_SINGLE, string $circleId = ''): void {
		}

		public function startAppSession(string $appId, int $appSerial = Member::APP_DEFAULT): void {
		}

		public function stopSession(): void {
		}

		public function getFederatedUser(string $federatedId, int $type = Member::TYPE_SINGLE): FederatedUser {
		}

		/** The $createTeamFolder parameter exists since Circles 35. */
		public function createCircle(string $name, ?FederatedUser $owner = null, bool $personal = false, bool $local = false, bool $createTeamFolder = true): Circle {
		}

		public function destroyCircle(string $singleId): void {
		}

		/** @return Circle[] */
		public function getCircles(?CircleProbe $probe = null, bool $refreshCache = false): array {
		}

		public function getCircle(string $singleId, ?CircleProbe $probe = null): Circle {
		}

		public function updateConfig(Circle $circle): void {
		}

		public function flagAsAppManaged(string $circleId, bool $enabled = true): void {
		}

		public function addMember(string $circleId, FederatedUser $federatedUser): Member {
		}

		public function levelMember(string $memberId, int $level): Member {
		}

		public function removeMember(string $memberId): void {
		}
	}
}

namespace OCA\Circles\Model {

	class FederatedUser {
	}

	class Circle {
		public const CFG_VISIBLE = 8;
		public const CFG_OPEN = 16;
		public const CFG_INVITE = 32;
		public const CFG_REQUEST = 64;
		public const CFG_FRIEND = 128;
		public const CFG_PROTECTED = 256;
		public const CFG_LOCAL = 4096;
		public const CFG_ROOT = 8192;
		public const CFG_FEDERATED = 32768;
		public const CFG_MOUNTPOINT = 65536;
		public const CFG_APP = 131072;

		public function getSingleId(): string {
		}

		public function getDisplayName(): string {
		}

		public function getSource(): int {
		}

		public function getConfig(): int {
		}

		public function setConfig(int $config): self {
		}

		public function getDescription(): string {
		}

		public function hasOwner(): bool {
		}

		public function getOwner(): Member {
		}

		/** @return Member[] */
		public function getMembers(int $limit = 0): array {
		}
	}

	class Member {
		public const LEVEL_NONE = 0;
		public const LEVEL_MEMBER = 1;
		public const LEVEL_MODERATOR = 4;
		public const LEVEL_ADMIN = 8;
		public const LEVEL_OWNER = 9;

		public const TYPE_SINGLE = 0;
		public const TYPE_USER = 1;
		public const TYPE_CIRCLE = 16;

		public const APP_CIRCLES = 10001;
		public const APP_DEFAULT = 11000;

		public const STATUS_INVITED = 'Invited';
		public const STATUS_REQUEST = 'Requesting';
		public const STATUS_MEMBER = 'Member';
		public const STATUS_BLOCKED = 'Blocked';

		public function getId(): string {
		}

		public function getSingleId(): string {
		}

		public function getUserId(): string {
		}

		public function getUserType(): int {
		}

		public function getDisplayName(): string {
		}

		public function getLevel(): int {
		}

		public function getStatus(): string {
		}
	}
}

namespace OCA\Circles\Model\Probes {

	class CircleProbe {
		public function includeSystemCircles(bool $include = true): self {
		}

		public function includeSingleCircles(bool $include = true): self {
		}

		public function includeHiddenCircles(bool $include = true): self {
		}

		public function includeBackendCircles(bool $include = true): self {
		}
	}
}

namespace OCA\Circles\Service {

	use OCA\Circles\Model\FederatedUser;

	class CircleService {
		public function updateName(string $circleId, string $name): array {
		}

		public function updateDescription(string $circleId, string $description): array {
		}
	}

	class FederatedUserService {
		public function getAppInitiator(string $appId, int $appNumber, string $appDisplayName = ''): FederatedUser {
		}
	}
}
