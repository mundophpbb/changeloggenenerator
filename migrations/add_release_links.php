<?php

namespace mundophpbb\changelog\migrations;

class add_release_links extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\\' . add_navigation_setting::class];
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'mphpbb_cl_releases' => [
					'download_url' => ['TEXT', ''],
					'support_url' => ['TEXT', ''],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'mphpbb_cl_releases' => ['download_url', 'support_url'],
			],
		];
	}
}
