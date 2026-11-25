<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Rewind\Domain;

/** Name based signals: ransom extensions and ransom notes. */
final class Signals {
	/** extensions that almost only ransomware uses */
	public const STRONG = [
		'locked', 'locky', 'encrypted', 'crypted', 'crypz', 'cerber', 'zepto', 'odin', 'thor', 'aesir', 'zzzzz', 'wncry', 'wnry', 'wcry',
		'ryk', 'conti', 'lockbit', 'blackcat', 'akira', 'djvu', 'phobos', 'dharma', 'kraken', 'rokku', 'nochance', 'petya', 'mamba',
		'ragnar', 'sodinokibi', 'revil', 'darkside', 'maze', 'egregor', 'ransom', 'payme', 'basta',
	];
	/** also used by harmless tools: only suspicious as an added second extension ("report.docx.enc") */
	public const WEAK = ['lock', 'encrypt', 'crypt', 'crypto', 'enc', 'cry', 'hive', 'royal', 'play', 'stop', 'wallet', 'arena', 'ecc', 'ezz', 'exx', 'abc', 'vvv', 'xxx', 'ttt', 'micro', 'pay'];
	private const DOCS = 'docx?|xlsx?|pptx?|pdf|jpe?g|png|gif|txt|odt|ods|odp|zip|sql|csv|mp4|mov|heic|rtf|md|json|xml|kdbx';

	public static function ransomExtension(string $ext): bool {
		return in_array(strtolower(ltrim($ext, '.')), self::STRONG, true);
	}

	/** name in the usual shapes: "a.docx.locked", "a.[id-1A2B].[mail@x.com].lockbit", "a.id-ABCD1234.crypted", "a.docx.enc" */
	public static function ransomName(string $basename): bool {
		$ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
		if (self::ransomExtension($ext)) {
			return true;
		}
		if (in_array($ext, self::WEAK, true) && preg_match('/\.(' . self::DOCS . ')\.[a-z0-9]{2,10}$/i', $basename) === 1) {
			return true;
		}
		return preg_match('/\.(\[[^\]]+\]){1,3}\.[a-z0-9]{3,10}$/i', $basename) === 1 || preg_match('/\.id[-_][a-z0-9]{6,}/i', $basename) === 1;
	}

	/** ransom note file names ("README_DECRYPT.txt", "HOW_TO_RECOVER_FILES.html", "!!!READ_ME!!!.txt") */
	public static function ransomNote(string $basename): bool {
		if (preg_match('/\.(txt|html?|hta|url|rtf|md)$/i', $basename) !== 1) {
			return false;
		}
		return preg_match('/(decrypt|restore[_\-\s]?(your[_\-\s]?)?files|recover[_\-\s]?(your[_\-\s]?)?files|how[_\-\s]?to[_\-\s]?(recover|restore|decrypt|get)|ransom|!!!|_readme_|read[_\-\s]?me[_\-\s]?(to|now|for)[_\-\s]?(decrypt|restore|recover|unlock))/i', $basename) === 1;
	}

	/** the file got a ransom name it did not have before */
	public static function suspiciousRename(string $old, string $new): bool {
		return self::ransomName(basename($new)) && !self::ransomName(basename($old));
	}
}
