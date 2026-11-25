<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Rewind\Service;

use OCA\Rewind\Domain\Detector;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** Runs the detector for an account/device, keeps incidents and decides about freezing. */
class IncidentService {
	public function __construct(
		private IDBConnection $db,
		private Journal $journal,
		private Config $config,
		private FreezeService $freeze,
		private AlertService $alerts,
	) {
	}

	/** evaluate one account/device now; returns the score */
	public function evaluate(string $uid, string $client, int $now): int {
		$c = $this->config->all();
		if (!$c['enabled'] || in_array($uid, $c['excluded_users'], true)) {
			return 0;
		}
		$win = (int)$c['window'];
		$since = max($now - $win, $this->freeze->lastEnded($uid) + 1);
		$w = $this->journal->window($uid, $client, $since);
		$base = Detector::baseline($this->journal->perWindow($uid, $now - 7 * 86400, $now - $win, $win));
		$r = Detector::score($w, $base, (int)$c['min_events']);
		$level = Detector::level($r['score'], (int)$c['observe_level'], (int)$c['freeze_level']);
		if ($level === 'ok') {
			return $r['score'];
		}
		$sample = $this->sample($uid, $client, $now - $win);
		$incident = $this->open($uid, $client, $now, $since, $r['score'], $r['reasons'], array_sum([$w['writes'], $w['renames'], $w['deletes'], $w['creates']]), $sample);

		if ($level === 'freeze' && $c['mode'] !== 'observe') {
			$this->freeze->freeze($uid, $c['mode'] === 'user' ? null : $client, implode(',', $r['reasons']), $r['score'], $now);
			$this->setStatus($incident['id'], 'frozen');
			if ($incident['new'] || $incident['status'] === 'open') {
				$this->alerts->incident($uid, $incident['id'], $r['score'], true, (string)$c['email']);
			}
		} elseif ($incident['new']) {
			$this->alerts->incident($uid, $incident['id'], $r['score'], false, (string)$c['email']);
		}
		return $r['score'];
	}

	private function sample(string $uid, string $client, int $since): array {
		$q = $this->db->getQueryBuilder();
		$q->select('path')->from('rw_journal')->where($q->expr()->eq('uid', $q->createNamedParameter($uid)))->andWhere($q->expr()->eq('client', $q->createNamedParameter($client)))
			->andWhere($q->expr()->gte('ts', $q->createNamedParameter($since, IQueryBuilder::PARAM_INT)))->andWhere($q->expr()->gt($q->createFunction('(flags & 3)'), $q->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->orderBy('id', 'DESC')->setMaxResults(12);
		return array_map(fn ($r) => (string)$r['path'], $q->executeQuery()->fetchAll());
	}

	/** @return array{id:int,new:bool,status:string} */
	private function open(string $uid, string $client, int $now, int $since, int $score, array $reasons, int $files, array $sample): array {
		$q = $this->db->getQueryBuilder();
		$q->select('id', 'status', 'score')->from('rw_incident')->where($q->expr()->eq('uid', $q->createNamedParameter($uid)))
			->andWhere($q->expr()->eq('client', $q->createNamedParameter($client)))->andWhere($q->expr()->in('status', $q->createNamedParameter(['open', 'frozen'], IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($q->expr()->gte('last_seen', $q->createNamedParameter(max($now - 900, $since), IQueryBuilder::PARAM_INT)))->orderBy('id', 'DESC')->setMaxResults(1);
		$row = $q->executeQuery()->fetch();
		if ($row) {
			$u = $this->db->getQueryBuilder();
			$u->update('rw_incident')->set('last_seen', $u->createNamedParameter($now, IQueryBuilder::PARAM_INT))->set('score', $u->createNamedParameter(max($score, (int)$row['score']), IQueryBuilder::PARAM_INT))
				->set('files', $u->createNamedParameter($files, IQueryBuilder::PARAM_INT))->set('reasons', $u->createNamedParameter(implode(',', $reasons)))
				->set('sample', $u->createNamedParameter(json_encode($sample)))->where($u->expr()->eq('id', $u->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT)))->executeStatement();
			return ['id' => (int)$row['id'], 'new' => false, 'status' => (string)$row['status']];
		}
		$ua = $this->db->getQueryBuilder();
		$ua->select('ua', 'ip', 'ts')->from('rw_journal')->where($ua->expr()->eq('uid', $ua->createNamedParameter($uid)))->andWhere($ua->expr()->eq('client', $ua->createNamedParameter($client)))->orderBy('id', 'DESC')->setMaxResults(1);
		$last = $ua->executeQuery()->fetch() ?: ['ua' => '', 'ip' => ''];
		// the attack began with the first suspicious event of the burst (else with the first event of the window)
		$started = $this->firstTs($uid, $client, $since, true) ?? $this->firstTs($uid, $client, $since, false) ?? $now;
		$i = $this->db->getQueryBuilder();
		$i->insert('rw_incident')->values(['uid' => $i->createNamedParameter($uid), 'client' => $i->createNamedParameter($client), 'ua' => $i->createNamedParameter((string)$last['ua']), 'ip' => $i->createNamedParameter((string)$last['ip']),
			'started' => $i->createNamedParameter($started, IQueryBuilder::PARAM_INT), 'last_seen' => $i->createNamedParameter($now, IQueryBuilder::PARAM_INT), 'score' => $i->createNamedParameter($score, IQueryBuilder::PARAM_INT),
			'files' => $i->createNamedParameter($files, IQueryBuilder::PARAM_INT), 'reasons' => $i->createNamedParameter(implode(',', $reasons)), 'sample' => $i->createNamedParameter(json_encode($sample)), 'status' => $i->createNamedParameter('open')])->executeStatement();
		return ['id' => $i->getLastInsertId(), 'new' => true, 'status' => 'open'];
	}

	private function firstTs(string $uid, string $client, int $since, bool $flagged): ?int {
		$f = $this->db->getQueryBuilder();
		$f->select($f->func()->min('ts'))->from('rw_journal')->where($f->expr()->eq('uid', $f->createNamedParameter($uid)))->andWhere($f->expr()->eq('client', $f->createNamedParameter($client)))
			->andWhere($f->expr()->gte('ts', $f->createNamedParameter($since, IQueryBuilder::PARAM_INT)))->andWhere($f->expr()->isNull('op_id'));
		if ($flagged) {
			$f->andWhere($f->expr()->gt($f->createFunction('(flags & 7)'), $f->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		}
		$v = $f->executeQuery()->fetchOne();
		return $v === false || $v === null ? null : (int)$v;
	}

	public function setStatus(int $id, string $status, ?int $op = null): void {
		$q = $this->db->getQueryBuilder();
		$q->update('rw_incident')->set('status', $q->createNamedParameter($status));
		if ($op !== null) {
			$q->set('op_id', $q->createNamedParameter($op, IQueryBuilder::PARAM_INT));
		}
		$q->where($q->expr()->eq('id', $q->createNamedParameter($id, IQueryBuilder::PARAM_INT)))->executeStatement();
	}

	public function find(int $id): ?array {
		$q = $this->db->getQueryBuilder();
		$q->select('*')->from('rw_incident')->where($q->expr()->eq('id', $q->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$r = $q->executeQuery()->fetch();
		return $r ? $this->shape($r) : null;
	}

	public function list(?string $uid, int $limit = 50): array {
		$q = $this->db->getQueryBuilder();
		$q->select('*')->from('rw_incident')->orderBy('id', 'DESC')->setMaxResults($limit);
		if ($uid !== null) {
			$q->where($q->expr()->eq('uid', $q->createNamedParameter($uid)));
		}
		return array_map(fn ($r) => $this->shape($r), $q->executeQuery()->fetchAll());
	}

	private function shape(array $r): array {
		return ['id' => (int)$r['id'], 'uid' => (string)$r['uid'], 'client' => (string)$r['client'], 'ua' => (string)$r['ua'], 'ip' => (string)$r['ip'], 'started' => (int)$r['started'], 'lastSeen' => (int)$r['last_seen'],
			'score' => (int)$r['score'], 'files' => (int)$r['files'], 'reasons' => $r['reasons'] === '' ? [] : explode(',', (string)$r['reasons']), 'sample' => json_decode((string)$r['sample'], true) ?: [], 'status' => (string)$r['status'], 'opId' => $r['op_id'] === null ? null : (int)$r['op_id']];
	}
}
