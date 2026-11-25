<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'api#status', 'url' => '/api/status', 'verb' => 'GET'],
		['name' => 'api#folders', 'url' => '/api/folders', 'verb' => 'GET'],
		['name' => 'api#histogram', 'url' => '/api/histogram', 'verb' => 'GET'],
		['name' => 'api#activity', 'url' => '/api/activity', 'verb' => 'GET'],
		['name' => 'api#preview', 'url' => '/api/rewind/preview', 'verb' => 'POST'],
		['name' => 'api#rewind', 'url' => '/api/rewind', 'verb' => 'POST'],
		['name' => 'api#undo', 'url' => '/api/rewind/undo', 'verb' => 'POST'],
		['name' => 'api#incident', 'url' => '/api/incident/{id}', 'verb' => 'GET', 'requirements' => ['id' => '\d+']],
		['name' => 'api#dismiss', 'url' => '/api/incident/{id}/dismiss', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		['name' => 'api#unfreeze', 'url' => '/api/unfreeze', 'verb' => 'POST'],
		['name' => 'admin#get', 'url' => '/admin/config', 'verb' => 'GET'],
		['name' => 'admin#save', 'url' => '/admin/config', 'verb' => 'POST'],
		['name' => 'admin#incidents', 'url' => '/admin/incidents', 'verb' => 'GET'],
		['name' => 'admin#unfreeze', 'url' => '/admin/unfreeze', 'verb' => 'POST'],
		['name' => 'admin#test', 'url' => '/admin/test', 'verb' => 'POST'],
	],
];
