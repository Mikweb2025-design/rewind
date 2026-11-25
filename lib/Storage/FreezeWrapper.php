<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Rewind\Storage;

use OC\Files\Storage\Wrapper\Wrapper;
use OCA\Rewind\Service\FreezeService;
use OCP\Files\ForbiddenException;

/** Makes the "files" tree of a frozen account/device read only. Reading, downloading, trash and versions stay untouched. */
class FreezeWrapper extends Wrapper {
	/** set by RewindService while it repairs files */
	public static bool $bypass = false;
	private FreezeService $freeze;
	private string $uid;
	private string $client;

	public function __construct($parameters) {
		parent::__construct($parameters);
		$this->freeze = $parameters['freeze'];
		$this->uid = $parameters['uid'];
		$this->client = $parameters['client'];
	}

	private function guard(string $path): void {
		if (self::$bypass || !str_starts_with(ltrim($path, '/'), 'files/') && trim($path, '/') !== 'files') {
			return;
		}
		if ($this->freeze->isFrozen($this->uid, $this->client)) {
			throw new ForbiddenException('Rewind: this device/account is frozen (read only)', false);
		}
	}

	public function mkdir(string $path): bool {
		$this->guard($path);
		return parent::mkdir($path);
	}

	public function rmdir(string $path): bool {
		$this->guard($path);
		return parent::rmdir($path);
	}

	public function unlink(string $path): bool {
		$this->guard($path);
		return parent::unlink($path);
	}

	public function touch(string $path, ?int $mtime = null): bool {
		$this->guard($path);
		return parent::touch($path, $mtime);
	}

	public function file_put_contents(string $path, mixed $data): int|float|false {
		$this->guard($path);
		return parent::file_put_contents($path, $data);
	}

	public function fopen(string $path, string $mode) {
		if ($mode !== 'r' && $mode !== 'rb') {
			$this->guard($path);
		}
		return parent::fopen($path, $mode);
	}

	public function rename(string $source, string $target): bool {
		$this->guard($source);
		$this->guard($target);
		return parent::rename($source, $target);
	}

	public function copy(string $source, string $target): bool {
		$this->guard($target);
		return parent::copy($source, $target);
	}

	public function writeStream(string $path, $stream, ?int $size = null): int {
		$this->guard($path);
		return parent::writeStream($path, $stream, $size);
	}

	public function moveFromStorage(\OCP\Files\Storage\IStorage $sourceStorage, string $sourceInternalPath, string $targetInternalPath): bool {
		$this->guard($targetInternalPath);
		return parent::moveFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
	}

	public function copyFromStorage(\OCP\Files\Storage\IStorage $sourceStorage, string $sourceInternalPath, string $targetInternalPath): bool {
		$this->guard($targetInternalPath);
		return parent::copyFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
	}
}
