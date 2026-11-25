<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Rewind\Service;

use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\Notification\IManager;
use Psr\Log\LoggerInterface;

/** In app notifications for the user and the admins plus an optional email. */
class AlertService {
	public function __construct(
		private IManager $notifications,
		private IGroupManager $groups,
		private IUserManager $users,
		private IMailer $mailer,
		private IURLGenerator $urls,
		private LoggerInterface $logger,
	) {
	}

	public function incident(string $uid, int $incidentId, int $score, bool $frozen, string $email): void {
		$recipients = [$uid];
		foreach ($this->groups->get('admin')?->getUsers() ?? [] as $a) {
			$recipients[] = $a->getUID();
		}
		foreach (array_unique($recipients) as $r) {
			try {
				$n = $this->notifications->createNotification();
				$n->setApp('rewind')->setUser($r)->setDateTime(new \DateTime())->setObject('incident', (string)$incidentId)
					->setSubject($frozen ? 'incident_frozen' : 'incident_watch', ['uid' => $uid, 'score' => $score]);
				$this->notifications->notify($n);
			} catch (\Throwable $e) {
				$this->logger->warning('Rewind notification failed: ' . $e->getMessage(), ['app' => 'rewind']);
			}
		}
		if ($email !== '') {
			$this->mail($email, $uid, $incidentId, $score, $frozen);
		}
	}

	public function mail(string $to, string $uid, int $incidentId, int $score, bool $frozen): void {
		try {
			$link = $this->urls->linkToRouteAbsolute('rewind.page.index') . '#incident/' . $incidentId;
			$m = $this->mailer->createMessage();
			$m->setTo([$to]);
			$m->setSubject($frozen ? '[Rewind] Ransomware shield: ' . $uid . ' frozen' : '[Rewind] Suspicious activity: ' . $uid);
			$m->setPlainBody(($frozen ? "The account or device of \"$uid\" was frozen (read only).\n" : "Suspicious file activity of \"$uid\" detected.\n")
				. "Score: $score / 100\n\nDetails and one-click rewind:\n$link\n");
			$this->mailer->send($m);
		} catch (\Throwable $e) {
			$this->logger->warning('Rewind mail failed: ' . $e->getMessage(), ['app' => 'rewind']);
		}
	}

	/** remove notifications of an incident (after rewind or dismissal) */
	public function clear(int $incidentId): void {
		$n = $this->notifications->createNotification();
		$n->setApp('rewind')->setObject('incident', (string)$incidentId);
		$this->notifications->markProcessed($n);
	}
}
