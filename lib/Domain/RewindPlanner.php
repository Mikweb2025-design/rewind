<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Rewind\Domain;

/**
 * Turns the journal entries of a folder after a moment T into an ordered list of steps that bring the folder back to the
 * state it had at T. Steps are meant to run in the given order (newest change first), each one idempotent.
 * Step types: rename (from,to) | restore (path, deleted) | trash (path,file_id) | rollback (path,file_id).
 */
final class RewindPlanner {
	/**
	 * @param list<array{id:int,action:string,path:string,old_path:?string,ts:int,file_id:?int}> $events journal rows with ts > T, any order
	 * @return array{steps:list<array<string,mixed>>,counts:array<string,int>}
	 */
	public static function plan(array $events): array {
		usort($events, fn ($a, $b) => ($b['ts'] <=> $a['ts']) ?: ($b['id'] <=> $a['id']));

		// a file created and deleted again inside the window: net nothing, drop both
		$created = [];
		$deleted = [];
		foreach ($events as $e) {
			if ($e['file_id'] === null) {
				continue;
			}
			if ($e['action'] === 'create' || $e['action'] === 'copy') {
				$created[$e['file_id']] = true;
			} elseif ($e['action'] === 'delete') {
				$deleted[$e['file_id']] = true;
			}
		}
		$noop = array_intersect_key($created, $deleted);

		$steps = [];
		$rolled = [];
		$trashed = [];
		foreach ($events as $e) {
			$id = $e['file_id'];
			if ($id !== null && isset($noop[$id]) && in_array($e['action'], ['create', 'copy', 'delete'], true)) {
				continue;
			}
			switch ($e['action']) {
				case 'rename':
					if ($e['old_path'] !== null && $e['old_path'] !== $e['path']) {
						$steps[] = ['type' => 'rename', 'from' => $e['path'], 'to' => $e['old_path'], 'file_id' => $id, 'ts' => $e['ts']];
					}
					break;
				case 'delete':
					$steps[] = ['type' => 'restore', 'path' => $e['path'], 'file_id' => $id, 'deleted' => $e['ts'], 'ts' => $e['ts']];
					break;
				case 'create':
				case 'copy':
					$key = $id ?? $e['path'];
					if (!isset($trashed[$key])) {
						$trashed[$key] = true;
						$steps[] = ['type' => 'trash', 'path' => $e['path'], 'file_id' => $id, 'ts' => $e['ts']];
					}
					break;
				case 'write':
					$key = $id ?? $e['path'];
					if (!isset($rolled[$key])) {
						$rolled[$key] = true;
						$steps[] = ['type' => 'rollback', 'path' => $e['path'], 'file_id' => $id, 'ts' => $e['ts']];
					}
					break;
			}
		}
		// a file that is rolled back and then trashed (created in the window and written later): the trash wins
		$trashIds = [];
		foreach ($steps as $s) {
			if ($s['type'] === 'trash' && $s['file_id'] !== null) {
				$trashIds[$s['file_id']] = true;
			}
		}
		$steps = array_values(array_filter($steps, fn ($s) => !($s['type'] === 'rollback' && $s['file_id'] !== null && isset($trashIds[$s['file_id']]))));

		$counts = ['rename' => 0, 'restore' => 0, 'trash' => 0, 'rollback' => 0];
		foreach ($steps as $s) {
			$counts[$s['type']]++;
		}
		return ['steps' => $steps, 'counts' => $counts];
	}

	/** is a path inside a folder (folder "/" = everything) */
	public static function inside(string $folder, string $path): bool {
		$folder = '/' . trim($folder, '/');
		return $folder === '/' || $path === $folder || str_starts_with($path, $folder . '/');
	}
}
