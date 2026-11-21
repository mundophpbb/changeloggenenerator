<?php

namespace mundophpbb\changelog\migrations;

class add_release_history extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\\' . add_release_links::class];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'mphpbb_cl_history' => [
					'COLUMNS' => [
						'history_id' => ['UINT', null, 'auto_increment'],
						'release_id' => ['UINT', 0],
						'source_revision' => ['UINT', 0],
						'changed_at' => ['TIMESTAMP', 0],
						'editor_id' => ['UINT', 0],
						'editor_name' => ['VCHAR_UNI:255', ''],
						'version' => ['VCHAR:64', ''],
						'previous_version' => ['VCHAR:64', ''],
						'features' => ['MTEXT_UNI', ''],
						'improvements' => ['MTEXT_UNI', ''],
						'fixes' => ['MTEXT_UNI', ''],
						'download_url' => ['TEXT', ''],
						'support_url' => ['TEXT', ''],
						'published_at' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'history_id',
					'KEYS' => ['release_revision' => ['UNIQUE', ['release_id', 'source_revision']]],
				],
			],
		];
	}

	public function revert_schema()
	{
		return ['drop_tables' => [$this->table_prefix . 'mphpbb_cl_history']];
	}
}
