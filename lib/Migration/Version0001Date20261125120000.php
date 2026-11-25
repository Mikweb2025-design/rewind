<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Rewind\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0001Date20261125120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('rw_journal')) {
			$t = $schema->createTable('rw_journal');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$t->addColumn('uid', Types::STRING, ['length' => 64, 'notnull' => true]);
			$t->addColumn('file_id', Types::BIGINT, ['notnull' => false]);
			$t->addColumn('path', Types::STRING, ['length' => 1000, 'notnull' => true]);
			$t->addColumn('old_path', Types::STRING, ['length' => 1000, 'notnull' => false]);
			$t->addColumn('action', Types::STRING, ['length' => 12, 'notnull' => true]);
			$t->addColumn('size', Types::BIGINT, ['notnull' => false]);
			$t->addColumn('ts', Types::INTEGER, ['notnull' => true]);
			$t->addColumn('client', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => '']);
			$t->addColumn('ua', Types::STRING, ['length' => 190, 'notnull' => false]);
			$t->addColumn('ip', Types::STRING, ['length' => 45, 'notnull' => false]);
			$t->addColumn('op_id', Types::BIGINT, ['notnull' => false]);
			$t->addColumn('flags', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$t->setPrimaryKey(['id']);
			$t->addIndex(['uid', 'ts'], 'rw_j_uid_ts');
			$t->addIndex(['file_id'], 'rw_j_file');
		}
		if (!$schema->hasTable('rw_freeze')) {
			$t = $schema->createTable('rw_freeze');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$t->addColumn('uid', Types::STRING, ['length' => 64, 'notnull' => true]);
			$t->addColumn('client', Types::STRING, ['length' => 16, 'notnull' => false]);
			$t->addColumn('reason', Types::STRING, ['length' => 64, 'notnull' => true, 'default' => '']);
			$t->addColumn('score', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$t->addColumn('started', Types::INTEGER, ['notnull' => true]);
			$t->addColumn('ended', Types::INTEGER, ['notnull' => false]);
			$t->addColumn('by_who', Types::STRING, ['length' => 64, 'notnull' => true, 'default' => 'auto']);
			$t->setPrimaryKey(['id']);
			$t->addIndex(['uid', 'ended'], 'rw_f_uid');
		}
		if (!$schema->hasTable('rw_incident')) {
			$t = $schema->createTable('rw_incident');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$t->addColumn('uid', Types::STRING, ['length' => 64, 'notnull' => true]);
			$t->addColumn('client', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => '']);
			$t->addColumn('ua', Types::STRING, ['length' => 190, 'notnull' => false]);
			$t->addColumn('ip', Types::STRING, ['length' => 45, 'notnull' => false]);
			$t->addColumn('started', Types::INTEGER, ['notnull' => true]);
			$t->addColumn('last_seen', Types::INTEGER, ['notnull' => true]);
			$t->addColumn('score', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$t->addColumn('files', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$t->addColumn('reasons', Types::STRING, ['length' => 255, 'notnull' => true, 'default' => '']);
			$t->addColumn('sample', Types::TEXT, ['notnull' => false]);
			$t->addColumn('status', Types::STRING, ['length' => 12, 'notnull' => true, 'default' => 'open']);
			$t->addColumn('op_id', Types::BIGINT, ['notnull' => false]);
			$t->setPrimaryKey(['id']);
			$t->addIndex(['uid', 'status'], 'rw_i_uid');
		}
		if (!$schema->hasTable('rw_operation')) {
			$t = $schema->createTable('rw_operation');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$t->addColumn('uid', Types::STRING, ['length' => 64, 'notnull' => true]);
			$t->addColumn('folder', Types::STRING, ['length' => 1000, 'notnull' => true]);
			$t->addColumn('target_ts', Types::INTEGER, ['notnull' => true]);
			$t->addColumn('created', Types::INTEGER, ['notnull' => true]);
			$t->addColumn('counts', Types::STRING, ['length' => 255, 'notnull' => true, 'default' => '{}']);
			$t->addColumn('result', Types::TEXT, ['notnull' => false]);
			$t->addColumn('status', Types::STRING, ['length' => 12, 'notnull' => true, 'default' => 'done']);
			$t->setPrimaryKey(['id']);
			$t->addIndex(['uid', 'created'], 'rw_o_uid');
		}
		return $schema;
	}
}
