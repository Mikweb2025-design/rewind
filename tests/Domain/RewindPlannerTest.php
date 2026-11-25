<?php

declare(strict_types=1);

namespace OCA\Rewind\Tests\Domain;

use OCA\Rewind\Domain\RewindPlanner;
use OCA\Rewind\Tests\TestCase;

final class RewindPlannerTest extends TestCase {
	private int $id = 0;

	private function ev(string $action, string $path, int $ts, ?int $file = null, ?string $old = null): array {
		return ['id' => ++$this->id, 'action' => $action, 'path' => $path, 'old_path' => $old, 'ts' => $ts, 'file_id' => $file];
	}

	public function testModifiedFileRollsBackOnce(): void {
		$p = RewindPlanner::plan([$this->ev('write', '/a.txt', 10, 1), $this->ev('write', '/a.txt', 20, 1), $this->ev('write', '/a.txt', 30, 1)]);
		$this->assertCount(1, $p['steps']);
		$this->assertSame('rollback', $p['steps'][0]['type']);
	}

	public function testDeletedFileIsRestored(): void {
		$p = RewindPlanner::plan([$this->ev('delete', '/a.txt', 10, 1)]);
		$this->assertSame('restore', $p['steps'][0]['type']);
		$this->assertSame(10, $p['steps'][0]['deleted']);
	}

	public function testCreatedFileIsTrashed(): void {
		$p = RewindPlanner::plan([$this->ev('create', '/n.txt', 10, 5), $this->ev('write', '/n.txt', 11, 5)]);
		$this->assertCount(1, $p['steps']);
		$this->assertSame('trash', $p['steps'][0]['type']);
	}

	public function testCreatedAndDeletedAgainIsNothing(): void {
		$p = RewindPlanner::plan([$this->ev('create', '/tmp.txt', 10, 7), $this->ev('delete', '/tmp.txt', 12, 7)]);
		$this->assertCount(0, $p['steps']);
	}

	public function testRenameChainRunsNewestFirst(): void {
		$p = RewindPlanner::plan([
			$this->ev('rename', '/b.txt', 10, 1, '/a.txt'),
			$this->ev('rename', '/c.txt', 20, 1, '/b.txt'),
		]);
		$this->assertSame('/c.txt', $p['steps'][0]['from']);
		$this->assertSame('/b.txt', $p['steps'][0]['to']);
		$this->assertSame('/b.txt', $p['steps'][1]['from']);
		$this->assertSame('/a.txt', $p['steps'][1]['to']);
	}

	public function testRansomwarePattern(): void {
		// encrypted copy created, original deleted, for 3 files
		$ev = [];
		foreach ([1, 2, 3] as $i) {
			$ev[] = $this->ev('create', "/d/f$i.txt.locked", 100 + $i, 100 + $i);
			$ev[] = $this->ev('delete', "/d/f$i.txt", 100 + $i, $i);
		}
		$p = RewindPlanner::plan($ev);
		$this->assertSame(3, $p['counts']['restore']);
		$this->assertSame(3, $p['counts']['trash']);
		$this->assertSame(0, $p['counts']['rollback']);
	}

	public function testOverwriteInPlaceRansomware(): void {
		$ev = [$this->ev('write', '/d/a.docx', 5, 1), $this->ev('write', '/d/b.docx', 6, 2), $this->ev('write', '/d/a.docx', 7, 1)];
		$p = RewindPlanner::plan($ev);
		$this->assertSame(2, $p['counts']['rollback']);
	}

	public function testCreatedThenWrittenAfterRename(): void {
		$p = RewindPlanner::plan([$this->ev('create', '/n.txt', 10, 9), $this->ev('rename', '/m.txt', 20, 9, '/n.txt'), $this->ev('write', '/m.txt', 30, 9)]);
		$types = array_column($p['steps'], 'type');
		$this->assertNotContains('rollback', $types);
		$this->assertContains('trash', $types);
	}

	public function testInside(): void {
		$this->assertTrue(RewindPlanner::inside('/', '/a/b.txt'));
		$this->assertTrue(RewindPlanner::inside('/Docs', '/Docs/x.txt'));
		$this->assertTrue(RewindPlanner::inside('/Docs/', '/Docs'));
		$this->assertFalse(RewindPlanner::inside('/Docs', '/Docs2/x.txt'));
	}
}
