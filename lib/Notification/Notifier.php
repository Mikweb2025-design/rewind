<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Rewind\Notification;

use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {
	public function __construct(private IFactory $l10n, private IURLGenerator $urls) {
	}

	public function getID(): string {
		return 'rewind';
	}

	public function getName(): string {
		return 'Rewind';
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'rewind') {
			throw new UnknownNotificationException();
		}
		$p = $notification->getSubjectParameters();
		$uid = (string)($p['uid'] ?? '');
		$frozen = $notification->getSubject() === 'incident_frozen';
		$texts = [
			'de' => [$frozen ? 'Ransomware-Schutz hat „%s“ eingefroren' : 'Verdächtige Dateiaktivität bei „%s“', 'Öffnen und mit einem Klick zurückspulen.'],
			'it' => [$frozen ? 'Lo scudo anti-ransomware ha bloccato «%s»' : 'Attività sospetta sui file di «%s»', 'Apri e torna indietro con un clic.'],
		][substr($languageCode, 0, 2)] ?? [$frozen ? 'Ransomware shield froze "%s"' : 'Suspicious file activity of "%s"', 'Open and rewind with one click.'];
		$notification->setParsedSubject(sprintf($texts[0], $uid))->setParsedMessage($texts[1])
			->setLink($this->urls->linkToRouteAbsolute('rewind.page.index') . '#incident/' . $notification->getObjectId())
			->setIcon($this->urls->getAbsoluteURL($this->urls->imagePath('rewind', 'app.svg')));
		return $notification;
	}
}
