<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Rewind\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** Persistence of the file journal. */
class Journal {
	public const F_ENCRYPTED = 1;
	public const F_RANSOM_NAME = 2;
	public const F_NOTE = 4;
	/** operation id stamped on events caused by a rewind (set by RewindService while it runs) */
	public static ?int $op = null;

	public function __construct(private IDBConnection $db) {
	}

	public function add(string $uid, string $action, string $path, ?string $old, ?int $fileId, ?int $size, int $ts, string $client, string $ua, string $ip, int $flags = 0): int {
		$q = $this->db->getQueryBuilder();
		$q->insert('rw_journal')->values([
			'uid' => $q->createNamedParameter($uid), 'file_id' => $q->createNamedParameter($fileId, $fileId === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_INT),
			'path' => $q->createNamedParameter(mb_substr($path, 0, 1000)), 'old_path' => $q->createNamedParameter($old === null ? null : mb_substr($old, 0, 1000)),
			'action' => $q->createNamedParameter($action), 'size' => $q->createNamedParameter($size), 'ts' => $q->createNamedParameter($ts, IQueryBuilder::PARAM_INT),
			'client' => $q->createNamedParameter($client), 'ua' => $q->createNamedParameter(mb_substr($ua, 0, 190)), 'ip' => $q->createNamedParameter(mb_substr($ip, 0, 45)),
			'op_id' => $q->createNamedParameter(self::$op), 'flags' => $q->createNamedParameter($flags, IQueryBuilder::PARAM_INT),
		])->executeStatement();
		return $q->getLastInsertId();
	}

	/** rows after $ts inside a folder, oldest first */
	public function after(string $uid, int $ts, string $folder = '/', int $limit = 100000): array {
		$q = $this->db->getQueryBuilder();
		$q->select('id', 'action', 'path', 'old_path', 'ts', 'file_id')->from('rw_journal')
			->where($q->expr()->eq('uid', $q->createNamedParameter($uid)))->andWhere($q->expr()->gt('ts', $q->createNamedParameter($ts, IQueryBuilder::PARAM_INT)))
			->orderBy('id', 'ASC')->setMaxResults($limit);
		$f = '/' . trim($folder, '/');
		if ($f !== '/') {
			$q->andWhere($q->expr()->orX($q->expr()->eq('path', $q->createNamedParameter($f)), $q->expr()->like('path', $q->createNamedParameter($this->db->escapeLikeParameter($f) . '/%'))));
		}
		$rows = [];
		foreach ($q->executeQuery()->fetchAll() as $r) {
			$rows[] = ['id' => (int)$r['id'], 'action' => (string)$r['action'], 'path' => (string)$r['path'], 'old_path' => $r['old_path'] === null ? null : (string)$r['old_path'],
				'ts' => (int)$r['ts'], 'file_id' => $r['file_id'] === null ? null : (int)$r['file_id']];
		}
		return $rows;
	}

	/** counts of the window for one account (and device) used by the detector */
	public function window(string $uid, ?string $client, int $since): array {
		$q = $this->db->getQueryBuilder();
		$q->select('action')->selectAlias($q->func()->count('*'), 'c')->from('rw_journal')
			->where($q->expr()->eq('uid', $q->createNamedParameter($uid)))->andWhere($q->expr()->gte('ts', $q->createNamedParameter($since, IQueryBuilder::PARAM_INT)))
			->andWhere($q->expr()->isNull('op_id'))->groupBy('action');
		if ($client !== null) {
			$q->andWhere($q->expr()->eq('client', $q->createNamedParameter($client)));
		}
		$by = [];
		foreach ($q->executeQuery()->fetchAll() as $r) {
			$by[(string)$r['action']] = (int)$r['c'];
		}
		$flag = function (int $bit) use ($uid, $client, $since): int {
			$q = $this->db->getQueryBuilder();
			$q->select($q->func()->count('*'))->from('rw_journal')->where($q->expr()->eq('uid', $q->createNamedParameter($uid)))
				->andWhere($q->expr()->gte('ts', $q->createNamedParameter($since, IQueryBuilder::PARAM_INT)))->andWhere($q->expr()->isNull('op_id'))
				->andWhere($q->expr()->gt($q->createFunction('(flags & ' . $bit . ')'), $q->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
			if ($client !== null) {
				$q->andWhere($q->expr()->eq('client', $q->createNamedParameter($client)));
			}
			return (int)$q->executeQuery()->fetchOne();
		};
		return ['writes' => $by['write'] ?? 0, 'renames' => $by['rename'] ?? 0, 'deletes' => $by['delete'] ?? 0, 'creates' => ($by['create'] ?? 0) + ($by['copy'] ?? 0),
			'encrypted' => $flag(self::F_ENCRYPTED), 'ransomNames' => $flag(self::F_RANSOM_NAME), 'notes' => $flag(self::F_NOTE)];
	}

	/** (uid, client) pairs with recent suspicious flags or high volume, for the safety net job */
	public function activeClients(int $since, int $minEvents): array {
		$q = $this->db->getQueryBuilder();
		$q->select('uid', 'client')->selectAlias($q->func()->count('*'), 'c')->from('rw_journal')
			->where($q->expr()->gte('ts', $q->createNamedParameter($since, IQueryBuilder::PARAM_INT)))->andWhere($q->expr()->isNull('op_id'))
			->groupBy('uid', 'client');
		$out = [];
		foreach ($q->executeQuery()->fetchAll() as $r) {
			if ((int)$r['c'] >= max(5, intdiv($minEvents, 4))) {
				$out[] = [(string)$r['uid'], (string)$r['client']];
			}
		}
		return $out;
	}

	/** events per window (for the baseline) of the last days */
	public function perWindow(string $uid, int $from, int $to, int $windowSec): array {
		$q = $this->db->getQueryBuilder();
		$q->select('ts')->from('rw_journal')->where($q->expr()->eq('uid', $q->createNamedParameter($uid)))
			->andWhere($q->expr()->gte('ts', $q->createNamedParameter($from, IQueryBuilder::PARAM_INT)))->andWhere($q->expr()->lt('ts', $q->createNamedParameter($to, IQueryBuilder::PARAM_INT)))
			->andWhere($q->expr()->isNull('op_id'))->setMaxResults(20000);
		$b = [];
		foreach ($q->executeQuery()->fetchAll() as $r) {
			$k = intdiv((int)$r['ts'], $windowSec);
			$b[$k] = ($b[$k] ?? 0) + 1;
		}
		return array_values($b);
	}

	/** activity buckets for the timeline chart: [ts, total, flagged] */
	public function histogram(string $uid, int $from, int $to, int $bucket, string $folder = '/'): array {
		$q = $this->db->getQueryBuilder();
		$q->select('ts', 'flags', 'path')->from('rw_journal')->where($q->expr()->eq('uid', $q->createNamedParameter($uid)))
			->andWhere($q->expr()->gte('ts', $q->createNamedParameter($from, IQueryBuilder::PARAM_INT)))->andWhere($q->expr()->lte('ts', $q->createNamedParameter($to, IQueryBuilder::PARAM_INT)))
			->setMaxResults(200000);
		$f = '/' . trim($folder, '/');
		if ($f !== '/') {
			$q->andWhere($q->expr()->like('path', $q->createNamedParameter($this->db->escapeLikeParameter($f) . '/%')));
		}
		$b = [];
		foreach ($q->executeQuery()->fetchAll() as $r) {
			$k = intdiv((int)$r['ts'] - $from, $bucket);
			$b[$k] ??= [0, 0];
			$b[$k][0]++;
			if (((int)$r['flags']) > 0) {
				$b[$k][1]++;
			}
		}
		$out = [];
		for ($k = 0, $n = intdiv($to - $from, $bucket); $k <= $n; $k++) {
			$out[] = [$from + $k * $bucket, $b[$k][0] ?? 0, $b[$k][1] ?? 0];
		}
		return $out;
	}

	/** grouped activity for the list: one row per (day-part, device, action) */
	public function recent(string $uid, int $from, int $to, string $folder = '/', int $limit = 300): array {
		$q = $this->db->getQueryBuilder();
		$q->select('id', 'action', 'path', 'old_path', 'ts', 'client', 'ua', 'ip', 'flags', 'op_id')->from('rw_journal')
			->where($q->expr()->eq('uid', $q->createNamedParameter($uid)))->andWhere($q->expr()->gte('ts', $q->createNamedParameter($from, IQueryBuilder::PARAM_INT)))
			->andWhere($q->expr()->lte('ts', $q->createNamedParameter($to, IQueryBuilder::PARAM_INT)))->orderBy('id', 'DESC')->setMaxResults($limit);
		$f = '/' . trim($folder, '/');
		if ($f !== '/') {
			$q->andWhere($q->expr()->like('path', $q->createNamedParameter($this->db->escapeLikeParameter($f) . '/%')));
		}
		return $q->executeQuery()->fetchAll();
	}

	public function oldest(string $uid): ?int {
		$q = $this->db->getQueryBuilder();
		$q->select($q->func()->min('ts'))->from('rw_journal')->where($q->expr()->eq('uid', $q->createNamedParameter($uid)));
		$v = $q->executeQuery()->fetchOne();
		return $v === false || $v === null ? null : (int)$v;
	}

	public function purge(int $before): int {
		$q = $this->db->getQueryBuilder();
		return $q->delete('rw_journal')->where($q->expr()->lt('ts', $q->createNamedParameter($before, IQueryBuilder::PARAM_INT)))->executeStatement();
	}

	public function deleteUser(string $uid): void {
		foreach (['rw_journal', 'rw_freeze', 'rw_incident', 'rw_operation'] as $t) {
			$q = $this->db->getQueryBuilder();
			$q->delete($t)->where($q->expr()->eq('uid', $q->createNamedParameter($uid)))->executeStatement();
		}
	}
}
