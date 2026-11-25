<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Rewind\Service;

use OCA\Files_Trashbin\Trash\ITrashManager;
use OCA\Files_Versions\Versions\IVersionManager;
use OCA\Rewind\Domain\RewindPlanner;
use OCA\Rewind\Storage\FreezeWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/** Previews and runs a rewind of a folder to a moment in the past. */
class RewindService {
	public function __construct(
		private Journal $journal,
		private IRootFolder $root,
		private IUserManager $users,
		private IDBConnection $db,
		private IncidentService $incidents,
		private AlertService $alerts,
		private LoggerInterface $logger,
	) {
	}

	/** every Nextcloud with the core apps has these; without them there is nothing to restore from */
	public static function available(): bool {
		return interface_exists(IVersionManager::class) && interface_exists(ITrashManager::class);
	}

	/**
	 * @return array{steps:list<array<string,mixed>>,counts:array<string,int>,problems:int,total:int,from:int}
	 */
	public function preview(string $uid, string $folder, int $ts, int $now): array {
		$plan = RewindPlanner::plan($this->journal->after($uid, $ts, $folder));
		$user = $this->user($uid);
		$ufolder = $this->root->getUserFolder($uid);
		$trash = $this->trashItems($user);
		$problems = 0;
		foreach ($plan['steps'] as &$s) {
			$s['status'] = $this->check($s, $user, $ufolder, $trash, $ts);
			if ($s['status'] !== 'ok') {
				$problems++;
			}
		}
		unset($s);
		return $plan + ['problems' => $problems, 'total' => count($plan['steps']), 'from' => $ts];
	}

	/** run the plan; returns the operation id */
	public function run(string $uid, string $folder, int $ts, int $now, string $by, ?int $incidentId = null): array {
		$prev = $this->preview($uid, $folder, $ts, $now);
		$op = $this->createOperation($uid, $folder, $ts, $now, $prev['counts']);
		Journal::$op = $op;
		FreezeWrapper::$bypass = true;
		$user = $this->user($uid);
		$ufolder = $this->root->getUserFolder($uid);
		$done = 0;
		$failed = [];
		try {
			foreach ($prev['steps'] as $s) {
				if ($s['status'] !== 'ok') {
					$failed[] = ['path' => $s['path'] ?? $s['from'], 'type' => $s['type'], 'why' => $s['status']];
					continue;
				}
				try {
					$this->apply($s, $user, $ufolder, $ts);
					$done++;
				} catch (\Throwable $e) {
					$failed[] = ['path' => $s['path'] ?? $s['from'], 'type' => $s['type'], 'why' => 'error'];
					$this->logger->warning('Rewind step failed: ' . $e->getMessage(), ['app' => 'rewind']);
				}
			}
		} finally {
			Journal::$op = null;
			FreezeWrapper::$bypass = false;
		}
		$status = $failed === [] ? 'done' : ($done > 0 ? 'partial' : 'failed');
		$q = $this->db->getQueryBuilder();
		$q->update('rw_operation')->set('status', $q->createNamedParameter($status))->set('result', $q->createNamedParameter(json_encode(['done' => $done, 'failed' => array_slice($failed, 0, 200)])))
			->where($q->expr()->eq('id', $q->createNamedParameter($op, IQueryBuilder::PARAM_INT)))->executeStatement();
		if ($incidentId !== null) {
			$this->incidents->setStatus($incidentId, 'rewound', $op);
			$this->alerts->clear($incidentId);
		}
		return ['op' => $op, 'status' => $status, 'done' => $done, 'failed' => $failed, 'counts' => $prev['counts']];
	}

	/** undo = rewind to just before the operation started: rollbacks keep the replaced content as a version, deletions go to the trash */
	public function undo(string $uid, int $opId, int $now, string $by): array {
		$op = $this->operation($uid, $opId);
		if ($op === null) {
			throw new \InvalidArgumentException('unknown operation');
		}
		$r = $this->run($uid, $op['folder'], $op['created'] - 1, $now, $by);
		$q = $this->db->getQueryBuilder();
		$q->update('rw_operation')->set('status', $q->createNamedParameter('undone'))->where($q->expr()->eq('id', $q->createNamedParameter($opId, IQueryBuilder::PARAM_INT)))->executeStatement();
		return $r;
	}

	public function operations(string $uid, int $limit = 20): array {
		$q = $this->db->getQueryBuilder();
		$q->select('*')->from('rw_operation')->where($q->expr()->eq('uid', $q->createNamedParameter($uid)))->orderBy('id', 'DESC')->setMaxResults($limit);
		return array_map(fn ($r) => ['id' => (int)$r['id'], 'folder' => (string)$r['folder'], 'target' => (int)$r['target_ts'], 'created' => (int)$r['created'],
			'counts' => json_decode((string)$r['counts'], true) ?: [], 'status' => (string)$r['status']], $q->executeQuery()->fetchAll());
	}

	private function operation(string $uid, int $id): ?array {
		foreach ($this->operations($uid, 200) as $o) {
			if ($o['id'] === $id) {
				return $o;
			}
		}
		return null;
	}

	private function createOperation(string $uid, string $folder, int $ts, int $now, array $counts): int {
		$q = $this->db->getQueryBuilder();
		$q->insert('rw_operation')->values(['uid' => $q->createNamedParameter($uid), 'folder' => $q->createNamedParameter('/' . trim($folder, '/')), 'target_ts' => $q->createNamedParameter($ts, IQueryBuilder::PARAM_INT),
			'created' => $q->createNamedParameter($now, IQueryBuilder::PARAM_INT), 'counts' => $q->createNamedParameter(json_encode($counts)), 'status' => $q->createNamedParameter('running')])->executeStatement();
		return $q->getLastInsertId();
	}

	/** the version manager and the trash backend move files without file events: write those changes into the journal ourselves (needed for undo) */
	private function log(string $uid, string $action, string $path, ?int $fileId): void {
		$this->journal->add($uid, $action, $path, null, $fileId, null, time(), 'rewind', 'Rewind', '', 0);
	}

	private function user(string $uid): IUser {
		return $this->users->get($uid) ?? throw new \InvalidArgumentException('unknown user');
	}

	/** @return list<\OCA\Files_Trashbin\Trash\ITrashItem> */
	private function trashItems(IUser $user): array {
		return \OC::$server->get(ITrashManager::class)->listTrashRoot($user);
	}

	private function node(Folder $uf, ?int $id, string $path): ?\OCP\Files\Node {
		if ($id !== null) {
			$n = $uf->getById($id);
			if ($n !== []) {
				return $n[0];
			}
		}
		try {
			return $uf->get(ltrim($path, '/'));
		} catch (NotFoundException) {
			return null;
		}
	}

	private function version(IUser $user, \OCP\Files\Node $node, int $ts): ?object {
		$best = null;
		foreach (\OC::$server->get(IVersionManager::class)->getVersionsForFile($user, $node) as $v) {
			if ($v->getTimestamp() <= $ts && ($best === null || $v->getTimestamp() > $best->getTimestamp())) {
				$best = $v;
			}
		}
		return $best;
	}

	private function trashFor(array $trash, string $path, int $deleted): ?object {
		$best = null;
		foreach ($trash as $t) {
			if ('/' . ltrim($t->getOriginalLocation(), '/') !== $path) {
				continue;
			}
			$d = abs($t->getDeletedTime() - $deleted);
			if ($d <= 120 && ($best === null || $d < abs($best->getDeletedTime() - $deleted))) {
				$best = $t;
			}
		}
		return $best;
	}

	private function check(array $s, IUser $user, Folder $uf, array $trash, int $ts): string {
		switch ($s['type']) {
			case 'rollback':
				$n = $this->node($uf, $s['file_id'], $s['path']);
				if ($n === null || !$n instanceof File) {
					return 'missing';
				}
				return $this->version($user, $n, $ts) === null ? 'no_version' : 'ok';
			case 'restore':
				return $this->trashFor($trash, $s['path'], $s['deleted']) === null ? 'not_in_trash' : 'ok';
			case 'trash':
				return $this->node($uf, $s['file_id'], $s['path']) === null ? 'gone' : 'ok';
			case 'rename':
				return $this->node($uf, $s['file_id'], $s['from']) === null ? 'missing' : 'ok';
		}
		return 'ok';
	}

	private function apply(array $s, IUser $user, Folder $uf, int $ts): void {
		switch ($s['type']) {
			case 'rollback':
				$n = $this->node($uf, $s['file_id'], $s['path']);
				$v = $n ? $this->version($user, $n, $ts) : null;
				if ($v === null) {
					throw new \RuntimeException('no version');
				}
				\OC::$server->get(IVersionManager::class)->rollback($v);
				$this->log($user->getUID(), 'write', $s['path'], $s['file_id']);
				return;
			case 'restore':
				$t = $this->trashFor($this->trashItems($user), $s['path'], $s['deleted']);
				if ($t === null) {
					throw new \RuntimeException('not in trash');
				}
				$t->getTrashBackend()->restoreItem($t);
				$restored = $this->node($uf, null, $s['path']);
				$this->log($user->getUID(), 'create', $s['path'], $restored?->getId());
				return;
			case 'trash':
				$this->node($uf, $s['file_id'], $s['path'])?->delete();
				return;
			case 'rename':
				$n = $this->node($uf, $s['file_id'], $s['from']);
				if ($n === null) {
					throw new \RuntimeException('missing');
				}
				$target = $uf->getPath() . '/' . ltrim($s['to'], '/');
				$dir = dirname($target);
				if (!$this->root->nodeExists($dir)) {
					$rel = trim(substr($dir, strlen($uf->getPath())), '/');
					$cur = $uf;
					foreach (explode('/', $rel) as $part) {
						$cur = $cur->nodeExists($part) ? $cur->get($part) : $cur->newFolder($part);
					}
				}
				$n->move($target);
				return;
		}
	}
}
