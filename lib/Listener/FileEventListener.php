<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Rewind\Listener;

use OCA\Rewind\Domain\Entropy;
use OCA\Rewind\Domain\Signals;
use OCA\Rewind\Service\Config;
use OCA\Rewind\Service\IncidentService;
use OCA\Rewind\Service\Journal;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\Events\Node\NodeCopiedEvent;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\File;
use OCP\Files\Node;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Server;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<Event> */
class FileEventListener implements IEventListener {
	/** file ids captured before a delete (after the delete the node has no id any more) */
	private static array $deleting = [];
	/** paths created in this request: the write event that follows a create is not a second change */
	private static array $created = [];
	private static int $analysed = 0;

	public function __construct(private Journal $journal, private Config $config, private IncidentService $incidents, private IUserSession $session, private IRequest $request, private LoggerInterface $logger) {
	}

	public function handle(Event $event): void {
		if (!$this->config->get('enabled')) {
			return;
		}
		try {
			$this->dispatch($event);
		} catch (\Throwable $e) {
			// never break a file operation because of the journal
			$this->logger->warning('Rewind journal: ' . $e->getMessage(), ['app' => 'rewind']);
		}
	}

	private function dispatch(Event $event): void {
		if ($event instanceof BeforeNodeDeletedEvent) {
			$n = $event->getNode();
			try {
				self::$deleting[$n->getPath()] = $n->getId();
			} catch (\Throwable) {
			}
			return;
		}
		if ($event instanceof NodeRenamedEvent) {
			$this->record('rename', $event->getTarget(), $event->getSource());
		} elseif ($event instanceof NodeCopiedEvent) {
			$this->record('copy', $event->getTarget(), null);
		} elseif ($event instanceof NodeCreatedEvent) {
			self::$created[$event->getNode()->getPath()] = true;
			$this->record('create', $event->getNode(), null);
		} elseif ($event instanceof NodeWrittenEvent) {
			$p = $event->getNode()->getPath();
			if (isset(self::$created[$p])) {
				unset(self::$created[$p]);
				return;
			}
			$this->record('write', $event->getNode(), null);
		} elseif ($event instanceof NodeDeletedEvent) {
			$this->record('delete', $event->getNode(), null);
		}
	}

	/** "/alice/files/Docs/a.txt" -> ['alice', '/Docs/a.txt'] */
	public static function split(string $path): ?array {
		return preg_match('#^/([^/]+)/files(/.*)?$#', $path, $m) === 1 ? [$m[1], ($m[2] ?? '') === '' ? '/' : $m[2]] : null;
	}

	private function record(string $action, Node $node, ?Node $old): void {
		$p = self::split($node->getPath());
		if ($p === null || $p[1] === '/') {
			return;
		}
		[$uid, $path] = $p;
		$oldPath = null;
		if ($old !== null) {
			$o = self::split($old->getPath());
			if ($o === null || $o[0] !== $uid) {
				// moved in from another account/mount: count as create
				$action = 'create';
			} else {
				$oldPath = $o[1];
			}
		}
		$id = null;
		if ($action === 'delete') {
			$id = self::$deleting[$node->getPath()] ?? null;
			unset(self::$deleting[$node->getPath()]);
		} else {
			try {
				$id = $node->getId();
			} catch (\Throwable) {
			}
		}
		$size = null;
		$flags = 0;
		$name = basename($path);
		if (in_array($action, ['create', 'rename', 'copy', 'write'], true)) {
			if (Signals::ransomName($name) && ($action !== 'write') && ($action !== 'rename' || Signals::suspiciousRename((string)$oldPath, $path))) {
				$flags |= Journal::F_RANSOM_NAME;
			}
			if (Signals::ransomNote($name) && $action !== 'rename') {
				$flags |= Journal::F_NOTE;
			}
			if ($node instanceof File && self::$analysed < 60) {
				try {
					$size = $node->getSize();
					if ($size >= 1024 && $action !== 'rename') {
						self::$analysed++;
						$h = $node->fopen('r');
						$sample = $h ? (string)fread($h, 4096) : '';
						$h && fclose($h);
						$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
						if (Entropy::looksEncrypted($ext, $sample)) {
							$flags |= Journal::F_ENCRYPTED;
						}
					}
				} catch (\Throwable) {
				}
			}
		}
		$ts = time();
		$client = self::client($this->request);
		$ua = (string)$this->request->getHeader('User-Agent');
		$ip = $this->request->getRemoteAddress();
		$id2 = $this->journal->add($uid, $action, $path, $oldPath, $id, $size, $ts, $client, $ua, $ip, $flags);
		// look at the numbers: always when something suspicious was just written, otherwise every 10th event
		if (Journal::$op === null && ($flags > 0 || $id2 % 10 === 0)) {
			$this->incidents->evaluate($uid, $client, $ts);
		}
	}

	/** short device id: stable for one app/IP combination (the session id changes with every request on WebDAV) */
	public static function client(IRequest $request): string {
		$ua = (string)$request->getHeader('User-Agent');
		$ua = preg_replace('#/[\d.]+#', '', $ua) ?? $ua;
		$ip = $request->getRemoteAddress();
		return $ip === '' && $ua === '' ? 'cli' : substr(sha1($ua . '|' . $ip), 0, 12);
	}
}
