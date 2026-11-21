<?php

namespace mundophpbb\changelog\controller;

use phpbb\request\request_interface;

/**
 * Builds ACP module URLs and keeps the release list filters across links.
 */
class admin_urls
{
	public const STATUS_FILTERS = [
		'all' => 'CL_FILTER_ALL',
		'draft' => 'CL_FILTER_DRAFTS',
		'published' => 'CL_FILTER_PUBLISHED',
	];

	private $action;
	private $product = 0;
	private $version = '';
	private $status = 'all';
	private $invalid = false;
	private $page = 1;

	/**
	 * @param string $action  The module's u_action
	 * @param int    $product Product whose list filters are carried over (0 for none)
	 */
	public function __construct($action, request_interface $request, $product)
	{
		$this->action = $action;
		$this->product = (int) $product;

		$version = htmlspecialchars_decode($request->variable('filter_version', '', true, request_interface::GET), ENT_QUOTES);
		$this->invalid = $version !== '' && !preg_match('/\A[0-9A-Za-z.+_-]{1,64}\z/', $version);
		$this->version = mb_substr($version, 0, 64, 'UTF-8');
		$status = $request->variable('filter_status', 'all', false, request_interface::GET);
		$this->status = isset(self::STATUS_FILTERS[$status]) ? $status : 'all';
		$this->page = max(1, min(100000, $request->variable('page', 1, false, request_interface::GET)));
	}

	public function base()
	{
		return $this->action;
	}

	public function page()
	{
		return $this->page;
	}

	public function filter_version()
	{
		return $this->version;
	}

	public function filter_status()
	{
		return $this->status;
	}

	public function filter_invalid()
	{
		return $this->invalid;
	}

	public function filtered()
	{
		return $this->version !== '' || $this->status !== 'all';
	}

	public function url($product, $release = null, $page = 1, $notice = '', $view = '', $keep_filters = true)
	{
		$url = $this->action . '&amp;product=' . (int) $product;
		if ($page > 1)
		{
			$url .= '&amp;page=' . (int) $page;
		}
		if ($notice !== '')
		{
			$url .= '&amp;notice=' . $notice;
		}
		if ($view !== '')
		{
			$url .= '&amp;view=' . $view;
		}
		if ($keep_filters && $this->product && (int) $product === $this->product)
		{
			if (!$this->invalid && $this->version !== '')
			{
				$url .= '&amp;filter_version=' . rawurlencode($this->version);
			}
			if ($this->status !== 'all')
			{
				$url .= '&amp;filter_status=' . $this->status;
			}
		}
		if ($release !== null)
		{
			$url .= '&amp;release=' . (int) $release . '#cl-editor';
		}

		return $url;
	}

	/**
	 * Splits a URL into a GET form action and its hidden fields.
	 */
	public static function form($url)
	{
		$url = htmlspecialchars_decode($url, ENT_QUOTES);
		parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $fields);

		return ['action' => parse_url($url, PHP_URL_PATH), 'fields' => $fields];
	}
}
