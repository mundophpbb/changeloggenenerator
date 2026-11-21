<?php

namespace mundophpbb\changelog;

use mundophpbb\changelog\migrations\install_changelog;

class ext extends \phpbb\extension\base
{
	public function is_enableable()
	{
		return PHP_VERSION_ID >= 70400
			&& version_compare($this->container->get('config')['version'], '3.3.0', '>=')
			&& version_compare($this->container->get('config')['version'], '4.0.0-dev', '<')
			&& class_exists(\ZipArchive::class);
	}

	protected function get_migration_file_list()
	{
		$legacy_name = install_changelog::class;
		$canonical_name = '\\' . $legacy_name;

		// Version 1.2.0 could register this migration without the leading backslash.
		if ($this->migrator->migration_state($legacy_name) !== false
			&& $this->migrator->migration_state($canonical_name) === false)
		{
			$db = $this->container->get('dbal.conn');
			$table = $this->container->getParameter('tables.migrations');
			$sql = 'UPDATE ' . $table . '
				SET ' . $db->sql_build_array('UPDATE', ['migration_name' => $canonical_name]) . "
				WHERE migration_name = '" . $db->sql_escape($legacy_name) . "'";
			$db->sql_query($sql);
			$this->migrator->load_migration_state();
		}

		return parent::get_migration_file_list();
	}
}
