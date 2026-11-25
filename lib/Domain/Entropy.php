<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Rewind\Domain;

/** Content checks on the first bytes of a file: encrypted data is random (high entropy) and loses its magic bytes. */
final class Entropy {
	/** types that are already compressed: entropy is always high, only the magic bytes tell */
	private const MAGIC = [
		'jpg' => ["\xFF\xD8\xFF"], 'jpeg' => ["\xFF\xD8\xFF"], 'png' => ["\x89PNG"], 'gif' => ['GIF8'],
		'pdf' => ['%PDF'], 'zip' => ["PK\x03\x04", "PK\x05\x06"], 'docx' => ["PK\x03\x04"], 'xlsx' => ["PK\x03\x04"], 'pptx' => ["PK\x03\x04"],
		'odt' => ["PK\x03\x04"], 'ods' => ["PK\x03\x04"], 'odp' => ["PK\x03\x04"], 'odg' => ["PK\x03\x04"], 'odf' => ["PK\x03\x04"], 'epub' => ["PK\x03\x04"], 'jar' => ["PK\x03\x04"], 'apk' => ["PK\x03\x04"], 'gz' => ["\x1F\x8B"], '7z' => ["7z\xBC\xAF"],
		'mp4' => ['ftyp'], 'mov' => ['ftyp', 'moov', 'wide', 'mdat'], 'mp3' => ['ID3', "\xFF\xFB", "\xFF\xF3", "\xFF\xF2"], 'heic' => ['ftyp'],
	];
	/** plain content: encrypted data stands out through entropy */
	private const PLAIN = ['txt', 'md', 'csv', 'json', 'xml', 'html', 'htm', 'php', 'js', 'css', 'py', 'java', 'c', 'cpp', 'h', 'sql', 'log', 'ini', 'yml', 'yaml', 'rtf', 'ics', 'vcf', 'svg'];

	public static function shannon(string $bytes): float {
		$n = strlen($bytes);
		if ($n === 0) {
			return 0.0;
		}
		$h = 0.0;
		foreach (count_chars($bytes, 1) as $c) {
			$p = $c / $n;
			$h -= $p * log($p, 2);
		}
		return $h;
	}

	/** magic bytes of the sample: true = match, false = mismatch, null = unknown type */
	public static function magicOk(string $ext, string $head): ?bool {
		$ext = strtolower($ext);
		if (!isset(self::MAGIC[$ext]) || strlen($head) < 4) {
			return null;
		}
		foreach (self::MAGIC[$ext] as $m) {
			if (str_starts_with($head, $m) || ($m === 'ftyp' && substr($head, 4, 4) === 'ftyp')
				|| (in_array($m, ['moov', 'wide', 'mdat'], true) && substr($head, 4, 4) === $m)) {
				return true;
			}
		}
		return false;
	}

	/** does the sample look like encrypted data? needs >= 1 KB, otherwise entropy means little */
	public static function looksEncrypted(string $ext, string $sample): bool {
		$ext = strtolower($ext);
		if (strlen($sample) < 1024) {
			return false;
		}
		$magic = self::magicOk($ext, $sample);
		if ($magic === false) {
			return self::shannon(substr($sample, 0, 4096)) > 7.5;
		}
		if ($magic === true) {
			return false;
		}
		$h = self::shannon(substr($sample, 0, 4096));
		if (in_array($ext, self::PLAIN, true)) {
			return $h > 7.5;
		}
		return Signals::ransomExtension($ext) && $h > 7.8;
	}
}
