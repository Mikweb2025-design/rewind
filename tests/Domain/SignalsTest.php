<?php

declare(strict_types=1);

namespace OCA\Rewind\Tests\Domain;

use OCA\Rewind\Domain\Detector;
use OCA\Rewind\Domain\Entropy;
use OCA\Rewind\Domain\Signals;
use OCA\Rewind\Tests\TestCase;

final class SignalsTest extends TestCase {
	public function testRansomNames(): void {
		foreach (['report.docx.locked', 'a.jpg.encrypted', 'x.[id-1A2B3C].[help@mail.com].lockbit', 'db.id-ABCD1234.txt', 'a.docx.enc', 'photo.png.lockbit'] as $n) {
			$this->assertTrue(Signals::ransomName($n), $n);
		}
		foreach (['report.docx', 'notes.txt', 'Main.java', 'game.play', 'backup.enc', 'photo.jpg', 'archive.tar.gz', 'a.b.c'] as $n) {
			$this->assertFalse(Signals::ransomName($n), $n);
		}
	}

	public function testRansomNotes(): void {
		foreach (['README_DECRYPT.txt', 'HOW_TO_RECOVER_FILES.html', '!!!READ_ME!!!.txt', 'RESTORE_FILES.txt', '_readme_.txt'] as $n) {
			$this->assertTrue(Signals::ransomNote($n), $n);
		}
		foreach (['README.md', 'readme.txt', 'how-to-cook.txt', 'notes.txt', 'restore.sh'] as $n) {
			$this->assertFalse(Signals::ransomNote($n), $n);
		}
	}

	public function testSuspiciousRename(): void {
		$this->assertTrue(Signals::suspiciousRename('a.docx', 'a.docx.locked'));
		$this->assertFalse(Signals::suspiciousRename('a.docx', 'b.docx'));
		$this->assertFalse(Signals::suspiciousRename('a.docx.locked', 'a.docx.locked.bak'));
	}

	public function testEntropy(): void {
		$text = str_repeat('Ciao, questo e un testo normale con parole italiane. ', 100);
		$rand = random_bytes(4096);
		$this->assertTrue(Entropy::shannon($text) < 5.0);
		$this->assertTrue(Entropy::shannon($rand) > 7.9);
		$this->assertFalse(Entropy::looksEncrypted('txt', $text));
		$this->assertTrue(Entropy::looksEncrypted('txt', $rand));
		$this->assertTrue(Entropy::looksEncrypted('locked', $rand));
		$this->assertFalse(Entropy::looksEncrypted('bin', $rand), 'unknown binary type is not enough');
	}

	public function testMagic(): void {
		$jpg = "\xFF\xD8\xFF\xE0" . random_bytes(4000);
		$this->assertFalse(Entropy::looksEncrypted('jpg', $jpg), 'real jpeg is high entropy but has its magic bytes');
		$this->assertTrue(Entropy::looksEncrypted('jpg', random_bytes(4000)), 'jpg without magic bytes');
		$this->assertFalse(Entropy::looksEncrypted('docx', "PK\x03\x04" . random_bytes(4000)));
		$this->assertTrue(Entropy::looksEncrypted('pdf', random_bytes(4000)));
		$this->assertFalse(Entropy::looksEncrypted('pdf', '%PDF-1.7' . str_repeat('a', 2000)));
		$this->assertFalse(Entropy::looksEncrypted('txt', 'short'), 'too small to judge');
	}

	public function testOpenDocumentGraphicsAreNotEncrypted(): void {
		// the files every new account gets (Templates/*.odg) are zip containers
		$this->assertFalse(Entropy::looksEncrypted('odg', "PK\x03\x04" . random_bytes(4000)));
		$this->assertFalse(Entropy::looksEncrypted('epub', "PK\x03\x04" . random_bytes(4000)));
		$this->assertTrue(Entropy::looksEncrypted('odg', random_bytes(4000)), 'a damaged container is still suspicious');
	}

	public function testDetectorQuiet(): void {
		$r = Detector::score(['writes' => 8, 'creates' => 4]);
		$this->assertSame(0, $r['score']);
		$this->assertSame('ok', Detector::level($r['score']));
	}

	public function testDetectorBigReorganisationIsNotAnAttack(): void {
		// 400 renames to normal names, no ransom signals: volume alone stays below the freeze level
		$r = Detector::score(['renames' => 400]);
		$this->assertTrue($r['score'] <= 25);
		$this->assertTrue(Detector::level($r['score']) === 'ok');
		$r = Detector::score(['creates' => 600]); // first sync of the desktop client
		$this->assertSame('ok', Detector::level($r['score']));
	}

	public function testDetectorAttack(): void {
		$r = Detector::score(['creates' => 20, 'deletes' => 20, 'ransomNames' => 20, 'encrypted' => 15, 'notes' => 1]);
		$this->assertSame('freeze', Detector::level($r['score']));
		$this->assertContains('encrypted_content', $r['reasons']);
		// early: first handful of files already crosses the observe level, a few more the freeze level
		$early = Detector::score(['creates' => 6, 'deletes' => 6, 'ransomNames' => 6, 'encrypted' => 6, 'notes' => 1], 0, 50);
		$this->assertSame('freeze', Detector::level($early['score']));
		$first = Detector::score(['creates' => 3, 'ransomNames' => 3, 'encrypted' => 3]);
		$this->assertTrue(Detector::level($first['score']) !== 'ok');
	}

	public function testBackupArchivesDoNotTrigger(): void {
		// a backup tool writes big zip/7z files: compressed (high entropy) but with correct magic bytes -> not counted as encrypted
		$r = Detector::score(['writes' => 30, 'creates' => 10, 'encrypted' => 0]);
		$this->assertSame('ok', Detector::level($r['score']));
	}

	public function testBaseline(): void {
		$this->assertSame(0, Detector::baseline([]));
		$this->assertSame(10, Detector::baseline([0, 5, 10, 15, 0]));
		// a heavy user: 300 events per window are normal, the limit rises accordingly
		$quiet = Detector::score(['writes' => 300], 0, 50);
		$heavy = Detector::score(['writes' => 300], 300, 50);
		$this->assertTrue($heavy['score'] < $quiet['score']);
	}
}
