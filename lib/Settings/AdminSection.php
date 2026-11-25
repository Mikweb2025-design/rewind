<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Rewind\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {
	public function __construct(private IURLGenerator $urls, private IL10N $l) {
	}

	public function getID(): string {
		return 'rewind';
	}

	public function getName(): string {
		return 'Rewind';
	}

	public function getPriority(): int {
		return 80;
	}

	public function getIcon(): string {
		return $this->urls->imagePath('rewind', 'app-dark.svg');
	}
}
