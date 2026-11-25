<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Rewind\AppInfo;

use OCA\Rewind\Listener\FileEventListener;
use OCA\Rewind\Listener\UserDeletedListener;
use OCA\Rewind\Notification\Notifier;
use OCA\Rewind\Service\Config;
use OCA\Rewind\Service\FreezeService;
use OCA\Rewind\Storage\FreezeWrapper;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\Events\Node\NodeCopiedEvent;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\Storage\IStorage;
use OCP\IRequest;
use OCP\User\Events\UserDeletedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'rewind';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerNotifierService(Notifier::class);
		foreach ([BeforeNodeDeletedEvent::class, NodeCreatedEvent::class, NodeWrittenEvent::class, NodeRenamedEvent::class, NodeCopiedEvent::class, NodeDeletedEvent::class] as $e) {
			$context->registerEventListener($e, FileEventListener::class);
		}
		$context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);
	}

	public function boot(IBootContext $context): void {
		$server = $context->getServerContainer();
		$config = $server->get(Config::class);
		if (!$config->get('enabled')) {
			return;
		}
		$config->touchInstall(time());
		\OCP\Util::connectHook('OC_Filesystem', 'preSetup', $this, 'addStorageWrapper');
	}

	/** hook handler: wrap the home storage of accounts that have an active freeze */
	public function addStorageWrapper(): void {
		$server = \OC::$server;
		\OC\Files\Filesystem::addStorageWrapper('rewind_freeze', function (string $mountPoint, IStorage $storage, IMountPoint $mount) use ($server) {
			// only the home storage of an account (its files/ tree), never external mounts
			if (preg_match('#^/([^/]+)/$#', $mountPoint, $m) !== 1) {
				return $storage;
			}
			$freeze = $server->get(FreezeService::class);
			if ($freeze->active($m[1]) === []) {
				return $storage;
			}
			return new FreezeWrapper(['storage' => $storage, 'freeze' => $freeze, 'uid' => $m[1], 'client' => FileEventListener::client($server->get(IRequest::class))]);
		}, 1);
	}
}
