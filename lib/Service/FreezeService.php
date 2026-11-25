<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Rewind\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** Freezes (read-only) a device or a whole account. */
class FreezeService {
	/** @var array<string,list<array{client:?string}>> per request cache of active freezes per user */
	private static array $cache = [];

	public function __construct(private IDBConnection $db) {
	}

	/** active freezes of a user: list of ['client' => null|string] */
	public function active(string $uid): array {
		if (isset(self::$cache[$uid])) {
			return self::$cache[$uid];
		}
		$q = $this->db->getQueryBuilder();
		$q->select('id', 'client', 'reason', 'score', 'started', 'by_who')->from('rw_freeze')
			->where($q->expr()->eq('uid', $q->createNamedParameter($uid)))->andWhere($q->expr()->isNull('ended'));
		return self::$cache[$uid] = $q->executeQuery()->fetchAll();
	}

	public function isFrozen(string $uid, string $client): bool {
		foreach ($this->active($uid) as $f) {
			if ($f['client'] === null || $f['client'] === '' || $f['client'] === $client) {
				return true;
			}
		}
		return false;
	}

	public function freeze(string $uid, ?string $client, string $reason, int $score, int $now, string $by = 'auto'): void {
		foreach ($this->active($uid) as $f) {
			if (($f['client'] ?? null) === $client) {
				return;
			}
		}
		$q = $this->db->getQueryBuilder();
		$q->insert('rw_freeze')->values(['uid' => $q->createNamedParameter($uid), 'client' => $q->createNamedParameter($client), 'reason' => $q->createNamedParameter(mb_substr($reason, 0, 64)),
			'score' => $q->createNamedParameter($score, IQueryBuilder::PARAM_INT), 'started' => $q->createNamedParameter($now, IQueryBuilder::PARAM_INT), 'by_who' => $q->createNamedParameter(mb_substr($by, 0, 64))])->executeStatement();
		unset(self::$cache[$uid]);
	}

	public function release(string $uid, int $now): int {
		$q = $this->db->getQueryBuilder();
		$n = $q->update('rw_freeze')->set('ended', $q->createNamedParameter($now, IQueryBuilder::PARAM_INT))
			->where($q->expr()->eq('uid', $q->createNamedParameter($uid)))->andWhere($q->expr()->isNull('ended'))->executeStatement();
		unset(self::$cache[$uid]);
		return $n;
	}

	/** moment of the last release or dismissal of a user: events before it are not counted again */
	public function lastEnded(string $uid): int {
		$q = $this->db->getQueryBuilder();
		$q->select($q->func()->max('ended'))->from('rw_freeze')->where($q->expr()->eq('uid', $q->createNamedParameter($uid)));
		return (int)$q->executeQuery()->fetchOne();
	}

	/** a closed marker row ("false alarm"): the detector starts counting from now */
	public function mark(string $uid, int $now, string $reason): void {
		$q = $this->db->getQueryBuilder();
		$q->insert('rw_freeze')->values(['uid' => $q->createNamedParameter($uid), 'client' => $q->createNamedParameter(null), 'reason' => $q->createNamedParameter($reason),
			'score' => $q->createNamedParameter(0, IQueryBuilder::PARAM_INT), 'started' => $q->createNamedParameter($now, IQueryBuilder::PARAM_INT), 'ended' => $q->createNamedParameter($now, IQueryBuilder::PARAM_INT), 'by_who' => $q->createNamedParameter('user')])->executeStatement();
	}

	/** all active freezes (admin) */
	public function all(): array {
		$q = $this->db->getQueryBuilder();
		$q->select('*')->from('rw_freeze')->where($q->expr()->isNull('ended'))->orderBy('started', 'DESC');
		return $q->executeQuery()->fetchAll();
	}
}
