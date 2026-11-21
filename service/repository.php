<?php

namespace mundophpbb\changelog\service;

use phpbb\db\driver\driver_interface;

class repository
{
	private $db;
	private $products;
	private $releases;
	private $history;

	public function __construct(driver_interface $db, $prefix)
	{
		$this->db = $db;
		$this->products = $prefix . 'mphpbb_cl_products';
		$this->releases = $prefix . 'mphpbb_cl_releases';
		$this->history = $prefix . 'mphpbb_cl_history';
	}

	public function products()
	{
		$sql = 'SELECT p.product_id, p.slug, p.title, p.download_url, p.support_url
			FROM ' . $this->products . ' p
			ORDER BY p.title, p.product_id';

		return $this->rows($this->db->sql_query($sql));
	}

	/**
	 * Release counts and latest published version for the given products (all when $ids is null).
	 *
	 * @return array<int, array{published: int, drafts: int, latest_version: string, latest_at: int}>
	 */
	public function product_stats(array $ids = null)
	{
		$where = $ids === null ? '' : ' WHERE ' . ($ids ? $this->db->sql_in_set('product_id', array_map('intval', $ids)) : '1 = 0');
		$sql = 'SELECT product_id,
				SUM(CASE WHEN published_at > 0 THEN 1 ELSE 0 END) AS published,
				SUM(CASE WHEN published_at = 0 THEN 1 ELSE 0 END) AS drafts,
				MAX(published_at) AS latest_at
			FROM ' . $this->releases . $where . '
			GROUP BY product_id';
		$stats = [];
		$latest = [];
		foreach ($this->rows($this->db->sql_query($sql)) as $row)
		{
			$stats[(int) $row['product_id']] = [
				'published' => (int) $row['published'],
				'drafts' => (int) $row['drafts'],
				'latest_version' => '',
				'latest_at' => (int) $row['latest_at'],
			];
			if ((int) $row['latest_at'] > 0)
			{
				$latest[] = '(product_id = ' . (int) $row['product_id'] . ' AND published_at = ' . (int) $row['latest_at'] . ')';
			}
		}

		foreach (array_chunk($latest, 100) as $chunk)
		{
			$sql = 'SELECT product_id, version
				FROM ' . $this->releases . '
				WHERE ' . implode(' OR ', $chunk) . '
				ORDER BY release_id ASC';
			foreach ($this->rows($this->db->sql_query($sql)) as $row)
			{
				$stats[(int) $row['product_id']]['latest_version'] = $row['version'];
			}
		}

		return $stats;
	}

	/**
	 * Products shown in the public catalogue, optionally filtered by an
	 * accent-insensitive search, one page at a time.
	 */
	public function public_products($search, $limit, $offset)
	{
		$sql = 'SELECT p.product_id, p.slug, p.title
			FROM ' . $this->products . ' p
			WHERE ' . $this->public_products_condition() . $this->title_search_sql($search) . '
			ORDER BY p.title, p.product_id';

		return $this->rows($this->db->sql_query_limit($sql, (int) $limit, (int) $offset));
	}

	public function count_public_products($search)
	{
		$row = $this->first('SELECT COUNT(p.product_id) AS total
			FROM ' . $this->products . ' p
			WHERE ' . $this->public_products_condition() . $this->title_search_sql($search));

		return (int) $row['total'];
	}

	public function product($product_id)
	{
		return $this->first('SELECT * FROM ' . $this->products . ' WHERE product_id = ' . (int) $product_id);
	}

	public function product_by_slug($slug)
	{
		return $this->first('SELECT * FROM ' . $this->products . " WHERE slug = '" . $this->db->sql_escape($slug) . "'");
	}

	public function add_product(array $data)
	{
		$data = $this->with_search_title($data);
		return $this->write('INSERT INTO ' . $this->products . ' ' . $this->db->sql_build_array('INSERT', $data))
			? (int) $this->db->sql_nextid() : 0;
	}

	public function update_product($id, array $data)
	{
		$data = $this->with_search_title($data);
		return $this->write('UPDATE ' . $this->products . ' SET ' . $this->db->sql_build_array('UPDATE', $data)
			. ' WHERE product_id = ' . (int) $id);
	}

	public function published_releases($product_id, $limit, $offset, $version_filter = '')
	{
		$sql = 'SELECT *' . $this->release_query($product_id, $version_filter, 'published')
			. ' ORDER BY published_at DESC, release_id DESC';
		return $this->rows($this->db->sql_query_limit($sql, $limit, $offset));
	}

	public function published_history($product_id, $from = null, $through = null)
	{
		$sql = 'SELECT version, published_at, features, improvements, fixes, download_url, support_url'
			. $this->release_query($product_id, '', 'published');
		if ($from !== null)
		{
			$sql .= ' AND published_at >= ' . (int) $from;
		}
		if ($through !== null)
		{
			$sql .= ' AND published_at <= ' . (int) $through;
		}
		$result = $this->db->sql_query($sql . ' ORDER BY published_at DESC, release_id DESC');

		try
		{
			while ($row = $this->db->sql_fetchrow($result))
			{
				yield $row;
			}
		}
		finally
		{
			$this->db->sql_freeresult($result);
		}
	}

	public function saved_releases($product_id, $version_filter, $status, $limit, $offset)
	{
		$sql = 'SELECT release_id, version, published_at' . $this->release_query($product_id, $version_filter, $status)
			. ' ORDER BY release_id DESC';
		return $this->rows($this->db->sql_query_limit($sql, $limit, $offset));
	}

	public function published_count($product_id, $version_filter = '')
	{
		return $this->count_releases($product_id, $version_filter, 'published');
	}

	public function count_releases($product_id, $version_filter, $status)
	{
		$row = $this->first('SELECT COUNT(*) AS total' . $this->release_query($product_id, $version_filter, $status));
		return (int) $row['total'];
	}

	private function release_query($product_id, $version_filter, $status)
	{
		$conditions = ['published' => ' AND published_at > 0', 'draft' => ' AND published_at = 0'];
		return ' FROM ' . $this->releases . ' WHERE product_id = ' . (int) $product_id
			. ($conditions[$status] ?? '') . $this->version_filter_sql($version_filter);
	}

	/**
	 * A product is public when it has a download or support link, or at least one published release.
	 * Wrapped in parentheses so further conditions can be appended with AND safely.
	 */
	private function public_products_condition()
	{
		return "(p.download_url <> '' OR p.support_url <> '' OR EXISTS (SELECT 1 FROM " . $this->releases . ' r
				WHERE r.product_id = p.product_id AND r.published_at > 0))';
	}

	private function title_search_sql($search)
	{
		$needle = text_search::normalize(trim((string) $search));
		if ($needle === '')
		{
			return '';
		}

		$wildcard = $this->db->get_any_char();
		return ' AND p.search_title ' . $this->db->sql_like_expression($wildcard . $needle . $wildcard);
	}

	private function with_search_title(array $data)
	{
		if (isset($data['title']))
		{
			$data['search_title'] = mb_substr(text_search::normalize($data['title']), 0, 255, 'UTF-8');
		}

		return $data;
	}

	private function version_filter_sql($filter)
	{
		if ($filter === '')
		{
			return '';
		}

		$wildcard = $this->db->get_any_char();
		return ' AND LOWER(version) ' . $this->db->sql_like_expression($wildcard . strtolower($filter) . $wildcard);
	}

	public function release($product_id, $release_id, $published_only = false)
	{
		$sql = 'SELECT * FROM ' . $this->releases
			. ' WHERE product_id = ' . (int) $product_id . ' AND release_id = ' . (int) $release_id;
		return $this->first($published_only ? $sql . ' AND published_at > 0' : $sql);
	}

	public function existing_versions($product_id, array $versions)
	{
		if (!$versions)
		{
			return [];
		}

		$sql = 'SELECT version FROM ' . $this->releases . ' WHERE product_id = ' . (int) $product_id
			. ' AND ' . $this->db->sql_in_set('version', $versions);
		return array_fill_keys(array_map('strtolower', array_column($this->rows($this->db->sql_query($sql)), 'version')), true);
	}

	public function release_for_version($product_id, $version)
	{
		return $this->first('SELECT * FROM ' . $this->releases
			. ' WHERE product_id = ' . (int) $product_id . " AND version = '" . $this->db->sql_escape($version) . "'");
	}

	public function release_history($release_id, $limit, $offset)
	{
		$sql = 'SELECT history_id, source_revision, changed_at, editor_name, version FROM ' . $this->history
			. ' WHERE release_id = ' . (int) $release_id . ' ORDER BY source_revision DESC';
		return $this->rows($this->db->sql_query_limit($sql, $limit, $offset));
	}

	public function history_entry($release_id, $history_id)
	{
		return $this->first('SELECT * FROM ' . $this->history
			. ' WHERE release_id = ' . (int) $release_id . ' AND history_id = ' . (int) $history_id);
	}

	/**
	 * Deletes a release and its edit history. Returns false when nothing was deleted.
	 */
	public function delete_release($product_id, $release_id)
	{
		$where = ' WHERE release_id = ' . (int) $release_id;
		$this->db->sql_return_on_error(true);
		try
		{
			if (!$this->db->sql_transaction('begin'))
			{
				return false;
			}
			if ($this->db->sql_query('DELETE FROM ' . $this->releases . $where . ' AND product_id = ' . (int) $product_id) === false
				|| (int) $this->db->sql_affectedrows() !== 1
				|| $this->db->sql_query('DELETE FROM ' . $this->history . $where) === false)
			{
				$this->db->sql_transaction('rollback');
				return false;
			}

			return (bool) $this->db->sql_transaction('commit');
		}
		catch (\Throwable $error)
		{
			$this->db->sql_transaction('rollback');
			throw $error;
		}
		finally
		{
			$this->db->sql_return_on_error(false);
		}
	}

	public function save_release(array $data, $product_id, $existing, array $editor)
	{
		if (!$existing)
		{
			$data['product_id'] = $product_id;
			return $this->write('INSERT INTO ' . $this->releases . ' ' . $this->db->sql_build_array('INSERT', $data))
				? (int) $this->db->sql_nextid() : 0;
		}

		$release_id = (int) $existing['release_id'];
		$revision = (int) $existing['revision'];
		$snapshot = array_intersect_key($existing, $data);
		$data['revision'] = $revision + 1;
		$sql = 'UPDATE ' . $this->releases . ' SET ' . $this->db->sql_build_array('UPDATE', $data)
			. ' WHERE release_id = ' . (int) $release_id
			. ' AND product_id = ' . (int) $product_id . ' AND revision = ' . (int) $revision;

		if (!array_diff_assoc($snapshot, $data))
		{
			return $this->write($sql) && (int) $this->db->sql_affectedrows() === 1 ? $release_id : 0;
		}

		$snapshot += [
			'release_id' => $release_id,
			'source_revision' => $revision,
			'changed_at' => time(),
			'editor_id' => $editor['id'],
			'editor_name' => $editor['name'],
		];
		$insert = 'INSERT INTO ' . $this->history . ' ' . $this->db->sql_build_array('INSERT', $snapshot);

		$this->db->sql_return_on_error(true);
		try
		{
			if (!$this->db->sql_transaction('begin'))
			{
				$this->db->sql_transaction('rollback');
				return 0;
			}
			if ($this->db->sql_query($sql) === false)
			{
				return 0;
			}
			if ((int) $this->db->sql_affectedrows() !== 1)
			{
				$this->db->sql_transaction('rollback');
				return 0;
			}
			if ($this->db->sql_query($insert) === false)
			{
				return 0;
			}

			return $this->db->sql_transaction('commit') ? $release_id : 0;
		}
		catch (\Throwable $error)
		{
			$this->db->sql_transaction('rollback');
			throw $error;
		}
		finally
		{
			$this->db->sql_return_on_error(false);
		}
	}

	private function first($sql)
	{
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		return $row;
	}

	private function rows($result)
	{
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);
		return $rows;
	}

	private function write($sql)
	{
		$this->db->sql_return_on_error(true);
		try
		{
			return $this->db->sql_query($sql) !== false;
		}
		finally
		{
			$this->db->sql_return_on_error(false);
		}
	}
}
