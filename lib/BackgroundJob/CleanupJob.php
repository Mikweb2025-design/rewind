<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Rewind\BackgroundJob;

use OCA\Rewind\Service\Config;
use OCA\Rewind\Service\Journal;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class CleanupJob extends TimedJob {
	public function __construct(ITimeFactory $time, private Journal $journal, private Config $config) {
		parent::__construct($time);
		$this->setInterval(86400);
	}

	protected function run($argument): void {
		$this->journal->purge($this->time->getTime() - (int)$this->config->get('retention_days') * 86400);
	}
}
