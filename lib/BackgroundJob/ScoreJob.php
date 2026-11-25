<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Rewind\BackgroundJob;

use OCA\Rewind\Service\Config;
use OCA\Rewind\Service\IncidentService;
use OCA\Rewind\Service\Journal;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

/** Safety net: looks at every active device every 5 minutes, also when the per-event check did not run. */
class ScoreJob extends TimedJob {
	public function __construct(ITimeFactory $time, private Journal $journal, private IncidentService $incidents, private Config $config) {
		parent::__construct($time);
		$this->setInterval(300);
	}

	protected function run($argument): void {
		if (!$this->config->get('enabled')) {
			return;
		}
		$now = $this->time->getTime();
		foreach ($this->journal->activeClients($now - (int)$this->config->get('window'), (int)$this->config->get('min_events')) as [$uid, $client]) {
			$this->incidents->evaluate($uid, $client, $now);
		}
	}
}
