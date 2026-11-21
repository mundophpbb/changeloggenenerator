<?php

namespace mundophpbb\changelog\migrations;

class add_product_support extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\\' . add_product_download::class];
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'mphpbb_cl_products' => [
					'support_url' => ['VCHAR:2048', ''],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'mphpbb_cl_products' => ['support_url'],
			],
		];
	}
}
