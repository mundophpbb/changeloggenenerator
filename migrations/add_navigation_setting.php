<?php

namespace mundophpbb\changelog\migrations;

class add_navigation_setting extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\\' . install_changelog::class];
	}

	public function update_data()
	{
		return [
			['config.add', ['mphpbb_cl_show_navigation', 1]],
		];
	}
}
