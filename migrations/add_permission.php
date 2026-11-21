<?php

namespace mundophpbb\changelog\migrations;

/**
 * Adds a dedicated "Can manage changelogs" permission. It starts as a copy of
 * a_board, so every administrator who could open the module before still can.
 */
class add_permission extends \phpbb\db\migration\migration
{
	private const MODULE = '\\mundophpbb\\changelog\\acp\\main_module';
	private const OLD_AUTH = 'ext_mundophpbb/changelog && acl_a_board';
	private const NEW_AUTH = 'ext_mundophpbb/changelog && acl_a_changelog';

	public static function depends_on()
	{
		return ['\\' . add_product_search::class];
	}

	public function update_data()
	{
		return [
			['permission.add', ['a_changelog', true, 'a_board']],
			['custom', [[$this, 'set_module_auth'], [self::NEW_AUTH]]],
		];
	}

	public function revert_data()
	{
		return [
			['custom', [[$this, 'set_module_auth'], [self::OLD_AUTH]]],
		];
	}

	public function set_module_auth($auth)
	{
		$sql = 'UPDATE ' . MODULES_TABLE . '
			SET ' . $this->db->sql_build_array('UPDATE', ['module_auth' => $auth]) . "
			WHERE module_class = 'acp'
				AND module_basename = '" . $this->db->sql_escape(self::MODULE) . "'";
		$this->db->sql_query($sql);
	}
}
