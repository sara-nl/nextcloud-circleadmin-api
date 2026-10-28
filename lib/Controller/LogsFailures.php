<?php

declare(strict_types=1);

namespace OCA\CirclesAdmin\Controller;

trait LogsFailures {

	/**
	 * Log a failed request. A refused request (unknown team, user or member,
	 * invalid input) is the caller's mistake and is logged as info, so it does
	 * not fill the log at the default level. Anything else is an error.
	 * Circles exceptions carry an HTTP-like code: 404 not found, 400 bad request.
	 *
	 * @param array{exception: \Throwable} $context
	 */
	private function logFailure(string $message, array $context): void {
		$e = $context['exception'];
		$refused = $e instanceof \InvalidArgumentException
			|| ($e->getCode() >= 400 && $e->getCode() < 500);
		if ($refused) {
			$this->logger->info($message, $context);
		} else {
			$this->logger->error($message, $context);
		}
	}
}
