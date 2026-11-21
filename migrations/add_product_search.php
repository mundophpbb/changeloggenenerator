<?php

namespace mundophpbb\changelog\migrations;

use mundophpbb\changelog\service\text_search;

/**
 * Stores an accent- and case-insensitive copy of each product title so the
 * public catalogue can be searched and paginated in SQL.
 */
class add_product_search extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists($this->table_prefix . 'mphpbb_cl_products', 'search_title');
	}

	public static function depends_on()
	{
		return ['\\' . add_product_support::class];
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'mphpbb_cl_products' => [
					'search_title' => ['VCHAR_UNI', ''],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'mphpbb_cl_products' => ['search_title'],
			],
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'fill_search_titles']]],
		];
	}

	public function fill_search_titles()
	{
		$table = $this->table_prefix . 'mphpbb_cl_products';
		$result = $this->db->sql_query('SELECT product_id, title FROM ' . $table);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		foreach ($rows as $row)
		{
			$search = mb_substr(text_search::normalize($row['title']), 0, 255, 'UTF-8');
			$this->db->sql_query('UPDATE ' . $table . '
				SET ' . $this->db->sql_build_array('UPDATE', ['search_title' => $search]) . '
				WHERE product_id = ' . (int) $row['product_id']);
		}
	}
}
