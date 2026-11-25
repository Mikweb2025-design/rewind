<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Rewind\Service;

use OCA\Rewind\Domain\Detector;
use OCP\IAppConfig;

/** App wide settings (admin page). */
class Config {
	public const APP = 'rewind';
	public const MODES = ['observe', 'client', 'user'];
	public const DEFAULTS = [
		'enabled' => true, 'mode' => 'observe', 'observe_level' => Detector::OBSERVE, 'freeze_level' => Detector::FREEZE,
		'window' => 300, 'min_events' => 50, 'retention_days' => 90, 'email' => '', 'excluded_users' => [], 'installed_at' => 0,
	];

	public function __construct(private IAppConfig $cfg) {
	}

	/** 'enabled' is reserved: Nextcloud stores the app's own enable state under that key. */
	private function key(string $k): string {
		return $k === 'enabled' ? 'protection' : $k;
	}

	public function all(): array {
		$out = [];
		foreach (self::DEFAULTS as $k => $d) {
			$out[$k] = $this->get($k);
		}
		return $out;
	}

	public function get(string $k): mixed {
		$d = self::DEFAULTS[$k];
		if (is_bool($d)) {
			return $this->cfg->getValueBool(self::APP, $this->key($k), $d);
		}
		if (is_int($d)) {
			return $this->cfg->getValueInt(self::APP, $this->key($k), $d);
		}
		if (is_array($d)) {
			return $this->cfg->getValueArray(self::APP, $this->key($k), $d);
		}
		return $this->cfg->getValueString(self::APP, $this->key($k), $d);
	}

	/** @return list<string> invalid fields */
	public function save(array $in): array {
		$bad = [];
		foreach ($in as $k => $v) {
			if (!array_key_exists($k, self::DEFAULTS) || $k === 'installed_at') {
				continue;
			}
			switch ($k) {
				case 'enabled':
					$this->cfg->setValueBool(self::APP, $this->key($k), filter_var($v, FILTER_VALIDATE_BOOLEAN));
					break;
				case 'mode':
					in_array($v, self::MODES, true) ? $this->cfg->setValueString(self::APP, $k, $v) : $bad[] = $k;
					break;
				case 'observe_level':
				case 'freeze_level':
				case 'window':
				case 'min_events':
				case 'retention_days':
					$r = ['observe_level' => [10, 90], 'freeze_level' => [20, 100], 'window' => [60, 3600], 'min_events' => [10, 5000], 'retention_days' => [7, 730]][$k];
					is_numeric($v) && (int)$v >= $r[0] && (int)$v <= $r[1] ? $this->cfg->setValueInt(self::APP, $k, (int)$v) : $bad[] = $k;
					break;
				case 'email':
					$v = trim((string)$v);
					$v === '' || filter_var($v, FILTER_VALIDATE_EMAIL) ? $this->cfg->setValueString(self::APP, $k, $v) : $bad[] = $k;
					break;
				case 'excluded_users':
					$list = is_string($v) ? preg_split('/[\s,]+/', $v) : (array)$v;
					$this->cfg->setValueArray(self::APP, $k, array_values(array_unique(array_filter(array_map('trim', array_map('strval', $list))))));
					break;
			}
		}
		return $bad;
	}

	/** first start: observe only for the first week */
	public function touchInstall(int $now): void {
		if ($this->get('installed_at') === 0) {
			$this->cfg->setValueInt(self::APP, 'installed_at', $now);
		}
	}
}
