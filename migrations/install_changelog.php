<?php

namespace mundophpbb\changelog\migrations;

class install_changelog extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v330\v330'];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'mphpbb_cl_products' => [
					'COLUMNS' => [
						'product_id' => ['UINT', null, 'auto_increment'],
						'slug' => ['VCHAR:80', ''],
						'title' => ['VCHAR_UNI:120', ''],
					],
					'PRIMARY_KEY' => 'product_id',
					'KEYS' => ['slug' => ['UNIQUE', 'slug']],
				],
				$this->table_prefix . 'mphpbb_cl_releases' => [
					'COLUMNS' => [
						'release_id' => ['UINT', null, 'auto_increment'],
						'product_id' => ['UINT', 0],
						'version' => ['VCHAR:64', ''],
						'previous_version' => ['VCHAR:64', ''],
						'features' => ['MTEXT_UNI', ''],
						'improvements' => ['MTEXT_UNI', ''],
						'fixes' => ['MTEXT_UNI', ''],
						'published_at' => ['TIMESTAMP', 0],
						'revision' => ['UINT', 1],
					],
					'PRIMARY_KEY' => 'release_id',
					'KEYS' => [
						'product_version' => ['UNIQUE', ['product_id', 'version']],
						'product_published' => ['INDEX', ['product_id', 'published_at']],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'mphpbb_cl_releases',
				$this->table_prefix . 'mphpbb_cl_products',
			],
		];
	}

	public function update_data()
	{
		return [
			['module.add', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_MPHPBB_CL']],
			['module.add', ['acp', 'ACP_MPHPBB_CL', [
				'module_basename' => '\mundophpbb\changelog\acp\main_module',
				'modes' => ['manage'],
			]]],
		];
	}
}
