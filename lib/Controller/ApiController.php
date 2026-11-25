<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Rewind\Controller;

use OCA\Rewind\AppInfo\Application;
use OCA\Rewind\Service\Config;
use OCA\Rewind\Service\FreezeService;
use OCA\Rewind\Service\IncidentService;
use OCA\Rewind\Service\Journal;
use OCA\Rewind\Service\RewindService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUserSession;

class ApiController extends Controller {
	public function __construct(
		IRequest $request,
		private IUserSession $session,
		private Journal $journal,
		private RewindService $rewind,
		private IncidentService $incidents,
		private FreezeService $freeze,
		private Config $config,
		private IRootFolder $root,
		private ITimeFactory $time,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	private function uid(): string {
		return $this->session->getUser()?->getUID() ?? throw new \RuntimeException('no user');
	}

	private static function folder(string $f): string {
		$f = '/' . trim(str_replace(['\\', '..'], '', $f), '/');
		return $f;
	}

	#[NoAdminRequired]
	public function status(): JSONResponse {
		$uid = $this->uid();
		$now = $this->time->getTime();
		$oldest = $this->journal->oldest($uid);
		$open = array_values(array_filter($this->incidents->list($uid, 20), fn ($i) => in_array($i['status'], ['open', 'frozen'], true)));
		$frozen = $this->freeze->active($uid);
		return new JSONResponse([
			'uid' => $uid, 'now' => $now, 'enabled' => (bool)$this->config->get('enabled'), 'mode' => $this->config->get('mode'),
			'available' => RewindService::available(), 'journalFrom' => $oldest, 'frozen' => $frozen !== [], 'frozenAll' => array_reduce($frozen, fn ($c, $f) => $c || $f['client'] === null, false),
			'incidents' => $open, 'recentIncidents' => array_slice($this->incidents->list($uid, 5), 0, 5), 'operations' => $this->rewind->operations($uid, 10),
			'retentionDays' => $this->config->get('retention_days'),
		]);
	}

	#[NoAdminRequired]
	public function folders(string $path = '/'): JSONResponse {
		$uid = $this->uid();
		$uf = $this->root->getUserFolder($uid);
		$p = self::folder($path);
		try {
			$node = $p === '/' ? $uf : $uf->get(ltrim($p, '/'));
		} catch (\Throwable) {
			return new JSONResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		$out = [];
		if ($node instanceof Folder) {
			foreach ($node->getDirectoryListing() as $n) {
				if ($n instanceof Folder) {
					$out[] = ['name' => $n->getName(), 'path' => rtrim($p, '/') . '/' . $n->getName()];
				}
			}
		}
		usort($out, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
		return new JSONResponse(['path' => $p, 'folders' => $out]);
	}

	#[NoAdminRequired]
	public function histogram(string $folder = '/', int $hours = 24): JSONResponse {
		$now = $this->time->getTime();
		$hours = max(1, min(2160, $hours));
		$bucket = $hours <= 6 ? 300 : ($hours <= 24 ? 900 : ($hours <= 168 ? 3600 : 21600));
		$from = intdiv($now - $hours * 3600, $bucket) * $bucket;
		return new JSONResponse(['from' => $from, 'to' => $now, 'bucket' => $bucket, 'buckets' => $this->journal->histogram($this->uid(), $from, $now, $bucket, self::folder($folder))]);
	}

	#[NoAdminRequired]
	public function activity(string $folder = '/', int $days = 3): JSONResponse {
		$now = $this->time->getTime();
		$rows = $this->journal->recent($this->uid(), $now - max(1, min(90, $days)) * 86400, $now, self::folder($folder), 400);
		// group by device + action + 10 minute block
		$g = [];
		foreach ($rows as $r) {
			$k = $r['client'] . '|' . $r['action'] . '|' . intdiv((int)$r['ts'], 600) . '|' . ($r['op_id'] === null ? 0 : 1);
			$g[$k] ??= ['action' => $r['action'], 'ts' => (int)$r['ts'], 'ua' => (string)$r['ua'], 'ip' => (string)$r['ip'], 'count' => 0, 'flagged' => 0, 'sample' => [], 'rewind' => $r['op_id'] !== null];
			$g[$k]['count']++;
			if ((int)$r['flags'] > 0) {
				$g[$k]['flagged']++;
			}
			if (count($g[$k]['sample']) < 3) {
				$g[$k]['sample'][] = (string)$r['path'];
			}
		}
		return new JSONResponse(['items' => array_values($g)]);
	}

	#[NoAdminRequired]
	public function preview(string $folder, int $ts): JSONResponse {
		$r = $this->rewind->preview($this->uid(), self::folder($folder), $ts, $this->time->getTime());
		$r['steps'] = array_slice($r['steps'], 0, 300);
		return new JSONResponse($r);
	}

	#[NoAdminRequired]
	public function rewind(string $folder, int $ts, ?int $incident = null): JSONResponse {
		$uid = $this->uid();
		if ($ts > $this->time->getTime()) {
			return new JSONResponse(['error' => 'future'], Http::STATUS_BAD_REQUEST);
		}
		if ($incident !== null) {
			$i = $this->incidents->find($incident);
			if ($i === null || $i['uid'] !== $uid) {
				$incident = null;
			}
		}
		return new JSONResponse($this->rewind->run($uid, self::folder($folder), $ts, $this->time->getTime(), $uid, $incident));
	}

	#[NoAdminRequired]
	public function undo(int $op): JSONResponse {
		try {
			return new JSONResponse($this->rewind->undo($this->uid(), $op, $this->time->getTime(), $this->uid()));
		} catch (\InvalidArgumentException) {
			return new JSONResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	public function incident(int $id): JSONResponse {
		$i = $this->incidents->find($id);
		if ($i === null || $i['uid'] !== $this->uid()) {
			return new JSONResponse(['error' => 'not_found'], Http::STATUS_NOT_FOUND);
		}
		return new JSONResponse($i);
	}

	#[NoAdminRequired]
	public function dismiss(int $id): JSONResponse {
		$i = $this->incident($id);
		if ($i->getStatus() !== Http::STATUS_OK) {
			return $i;
		}
		$this->incidents->setStatus($id, 'dismissed');
		$this->freeze->release($this->uid(), $this->time->getTime());
		$this->freeze->mark($this->uid(), $this->time->getTime(), 'dismissed');
		return new JSONResponse(['ok' => true]);
	}

	/** an account is unfrozen by its owner (the page asks for confirmation); admins can do it for everybody on the admin page */
	#[NoAdminRequired]
	public function unfreeze(): JSONResponse {
		return new JSONResponse(['released' => $this->freeze->release($this->uid(), $this->time->getTime())]);
	}
}
