<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Rewind\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\Util;

class Admin implements ISettings {
	public function getForm(): TemplateResponse {
		Util::addScript('rewind', 'rw-i18n');
		Util::addScript('rewind', 'rw-admin');
		Util::addStyle('rewind', 'rewind');
		return new TemplateResponse('rewind', 'admin', [], '');
	}

	public function getSection(): string {
		return 'rewind';
	}

	public function getPriority(): int {
		return 10;
	}
}
