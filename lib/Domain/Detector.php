<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Rewind\Domain;

/** Attack score 0-100 from the signals of one account/device in a time window. */
final class Detector {
	public const OBSERVE = 40;
	public const FREEZE = 70;

	/**
	 * @param array{writes:int,renames:int,deletes:int,creates:int,ransomNames:int,notes:int,encrypted:int} $w counts in the window
	 * @param int $baseline events per window this account usually causes (median of its busy windows)
	 * @param int $minEvents below this many events volume alone is never a signal
	 * @return array{score:int,reasons:list<string>}
	 */
	public static function score(array $w, int $baseline = 0, int $minEvents = 50): array {
		$w += ['writes' => 0, 'renames' => 0, 'deletes' => 0, 'creates' => 0, 'ransomNames' => 0, 'notes' => 0, 'encrypted' => 0];
		$events = $w['writes'] + $w['renames'] + $w['deletes'] + $w['creates'];
		$reasons = [];
		$score = 0;

		$limit = max($minEvents, 5 * $baseline);
		if ($events >= $minEvents / 2) {
			$p = (int)min(25, round(25 * $events / $limit));
			if ($p > 0) {
				$score += $p;
				$reasons[] = 'volume';
			}
		}
		if ($w['ransomNames'] >= 3) {
			$score += (int)min(35, 14 + 3 * $w['ransomNames']);
			$reasons[] = 'ransom_names';
		}
		if ($w['notes'] >= 1) {
			$score += $w['notes'] >= 3 ? 25 : 15;
			$reasons[] = 'ransom_note';
		}
		if ($w['encrypted'] >= 3) {
			$score += (int)min(40, 6 * $w['encrypted']);
			$reasons[] = 'encrypted_content';
		}
		if ($w['deletes'] >= $minEvents / 2 && $w['creates'] + $w['renames'] >= $w['deletes'] / 2) {
			$score += 10;
			$reasons[] = 'replace_pattern';
		}
		return ['score' => (int)min(100, $score), 'reasons' => $reasons];
	}

	/** median events per busy window from per-window counts */
	public static function baseline(array $perWindow): int {
		$v = array_values(array_filter(array_map('intval', $perWindow), fn ($x) => $x > 0));
		if ($v === []) {
			return 0;
		}
		sort($v);
		$n = count($v);
		return (int)($n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2);
	}

	public static function level(int $score, int $observe = self::OBSERVE, int $freeze = self::FREEZE): string {
		return $score >= $freeze ? 'freeze' : ($score >= $observe ? 'observe' : 'ok');
	}
}
