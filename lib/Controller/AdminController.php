<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Rewind\Controller;

use OCA\Rewind\AppInfo\Application;
use OCA\Rewind\Service\AlertService;
use OCA\Rewind\Service\Config;
use OCA\Rewind\Service\FreezeService;
use OCA\Rewind\Service\IncidentService;
use OCA\Rewind\Service\RewindService;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;
use OCP\IUserSession;

/** Admin only (no NoAdminRequired attribute). */
class AdminController extends Controller {
	public function __construct(IRequest $request, private Config $config, private IncidentService $incidents, private FreezeService $freeze, private AlertService $alerts,
		private IUserSession $session, private ITimeFactory $time, private IAppManager $apps) {
		parent::__construct(Application::APP_ID, $request);
	}

	public function get(): JSONResponse {
		return new JSONResponse(['config' => $this->config->all(), 'checks' => [
			'versions' => $this->apps->isEnabledForAnyone('files_versions'), 'trashbin' => $this->apps->isEnabledForAnyone('files_trashbin'), 'api' => RewindService::available(),
		], 'frozen' => $this->freeze->all(), 'incidents' => $this->incidents->list(null, 30)]);
	}

	public function save(): JSONResponse {
		$bad = $this->config->save($this->request->getParams());
		return new JSONResponse(['config' => $this->config->all(), 'bad' => $bad], $bad === [] ? Http::STATUS_OK : Http::STATUS_BAD_REQUEST);
	}

	public function incidents(): JSONResponse {
		return new JSONResponse(['incidents' => $this->incidents->list(null, 100)]);
	}

	public function unfreeze(string $uid): JSONResponse {
		return new JSONResponse(['released' => $this->freeze->release($uid, $this->time->getTime())]);
	}

	public function test(): JSONResponse {
		$email = (string)$this->config->get('email');
		if ($email === '') {
			return new JSONResponse(['error' => 'no_email'], Http::STATUS_BAD_REQUEST);
		}
		$this->alerts->mail($email, $this->session->getUser()?->getUID() ?? 'test', 0, 99, true);
		return new JSONResponse(['ok' => true]);
	}
}
