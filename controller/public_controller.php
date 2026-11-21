<?php

namespace mundophpbb\changelog\controller;

use mundophpbb\changelog\service\release_notes;
use mundophpbb\changelog\service\repository;
use phpbb\auth\auth;
use phpbb\controller\helper;
use phpbb\exception\http_exception;
use phpbb\language\language;
use phpbb\request\request_interface;
use phpbb\template\template;
use phpbb\user;

class public_controller
{
	private const PAGE_SIZE = 12;

	private $template;
	private $language;
	private $user;
	private $helper;
	private $repository;
	private $request;
	private $auth;

	public function __construct(template $template, language $language, user $user, helper $helper,
		repository $repository, request_interface $request, auth $auth)
	{
		$this->template = $template;
		$this->language = $language;
		$this->user = $user;
		$this->helper = $helper;
		$this->repository = $repository;
		$this->request = $request;
		$this->auth = $auth;
	}

	public function index()
	{
		$this->language->add_lang('common', 'mundophpbb/changelog');

		$search = htmlspecialchars_decode($this->request->variable('q', '', true, request_interface::GET), ENT_QUOTES);
		$search = mb_substr(trim($search), 0, 120, 'UTF-8');
		$total = $this->repository->count_public_products($search);
		$last_page = max(1, (int) ceil($total / self::PAGE_SIZE));
		$page = max(1, min($last_page, $this->request->variable('page', 1, false, request_interface::GET)));
		$products = $total ? $this->repository->public_products($search, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE) : [];
		$stats = $products ? $this->repository->product_stats(array_column($products, 'product_id')) : [];
		foreach ($products as &$product)
		{
			$product = $this->with_summary($product, $stats[(int) $product['product_id']] ?? []);
			$product['url'] = $this->helper->route('mundophpbb_changelog_product', ['slug' => $product['slug']]);
		}
		unset($product);

		$this->assign_navlinks();

		$index_url = $this->catalogue_url();
		parse_str(htmlspecialchars_decode(parse_url($index_url, PHP_URL_QUERY) ?? '', ENT_QUOTES), $query);
		$this->template->assign_vars([
			'CL_TITLE' => $this->language->lang('CL_PUBLIC_INDEX'),
			'CL_PRODUCTS' => $products,
			'CL_CATALOGUE_TOTAL' => $total,
			'CL_CATALOGUE_URL' => $index_url,
			'CL_CATALOGUE_ACTION' => parse_url($index_url, PHP_URL_PATH),
			'CL_CATALOGUE_SID' => $query['sid'] ?? '',
			'CL_CATALOGUE_SEARCH' => $search,
			'CL_CATALOGUE_PAGE' => $page,
			'CL_CATALOGUE_LAST_PAGE' => $last_page,
			'CL_PREVIOUS_URL' => $page > 1 ? $this->catalogue_url($page - 1, $search) : '',
			'CL_NEXT_URL' => $page < $last_page ? $this->catalogue_url($page + 1, $search) : '',
		]);

		return $this->helper->render('mphpbb_changelog_public.html', $this->language->lang('CL_PUBLIC_INDEX'));
	}

	private function catalogue_url($page = 1, $search = '')
	{
		$params = [];
		if ($page > 1)
		{
			$params['page'] = $page;
		}
		if ($search !== '')
		{
			$params['q'] = $search;
		}

		return $this->helper->route('mundophpbb_changelog_index', $params);
	}

	public function product($slug, $page = 1)
	{
		$this->language->add_lang('common', 'mundophpbb/changelog');

		$product = $this->repository->product_by_slug($slug);
		if (!$product)
		{
			throw new http_exception(404, 'CL_ERR_PUBLIC_NOT_FOUND');
		}
		$links = release_notes::links($product, release_notes::PRODUCT_LINKS);
		$published = $this->repository->published_count($product['product_id']);
		if (!$published && !$links)
		{
			throw new http_exception(404, 'CL_ERR_PUBLIC_NOT_FOUND');
		}

		$filter = htmlspecialchars_decode($this->request->variable('version', '', true, request_interface::GET), ENT_QUOTES);
		$valid_filter = $filter === '' || preg_match('/\A[0-9A-Za-z.+_-]{1,64}\z/', $filter);
		$matches = $filter === '' ? $published : ($valid_filter ? $this->repository->published_count($product['product_id'], $filter) : 0);
		$page = (int) $page;
		$last_page = max(1, (int) ceil($matches / self::PAGE_SIZE));
		if ($page < 1 || $page > $last_page)
		{
			throw new http_exception(404, 'CL_ERR_PUBLIC_NOT_FOUND');
		}

		$releases = $matches ? $this->repository->published_releases(
			$product['product_id'], self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE, $filter
		) : [];
		if ($filter === '' && !$releases && !$links)
		{
			throw new http_exception(404, 'CL_ERR_PUBLIC_NOT_FOUND');
		}

		$history_url = $this->page_url($slug, 1);
		parse_str(htmlspecialchars_decode(parse_url($history_url, PHP_URL_QUERY) ?? '', ENT_QUOTES), $query);
		$this->template->assign_vars([
			'CL_HISTORY_URL' => $published ? $history_url : '',
			'CL_SEARCH_ACTION' => parse_url($history_url, PHP_URL_PATH),
			'CL_SEARCH_SID' => $query['sid'] ?? '',
			'CL_SEARCH_VERSION' => mb_substr($filter, 0, 64, 'UTF-8'),
			'CL_SEARCH_INVALID' => !$valid_filter,
			'CL_LATEST_RELEASE_ID' => $filter === '' && $page === 1 && $releases ? (int) $releases[0]['release_id'] : 0,
			'CL_PREVIOUS_URL' => $page > 1 ? $this->page_url($slug, $page - 1, $filter) : '',
			'CL_NEXT_URL' => $page < $last_page ? $this->page_url($slug, $page + 1, $filter) : '',
		]);

		return $this->render_releases($product, $releases);
	}

	public function release($slug, $release_id)
	{
		$this->language->add_lang('common', 'mundophpbb/changelog');

		$product = $this->repository->product_by_slug($slug);
		$release = $product ? $this->repository->release($product['product_id'], $release_id, true) : false;
		if (!$release)
		{
			throw new http_exception(404, 'CL_ERR_PUBLIC_NOT_FOUND');
		}

		$this->template->assign_var('CL_PRODUCT_URL', $this->page_url($slug, 1));

		return $this->render_releases($product, [$release], $release['version']);
	}

	public function preview($slug)
	{
		$this->language->add_lang('common', 'mundophpbb/changelog');
		if (empty($this->user->data['session_admin']) || !$this->auth->acl_get('a_changelog'))
		{
			throw new http_exception(403, 'CL_PREVIEW_FORBIDDEN');
		}
		if (!check_form_key(admin_controller::FORM))
		{
			throw new http_exception(403, 'FORM_INVALID');
		}

		$product = $this->repository->product_by_slug($slug);
		if (!$product)
		{
			throw new http_exception(404, 'CL_ERR_PUBLIC_NOT_FOUND');
		}

		$release = release_notes::from_editor($this->request);
		$error = release_notes::editor_error($release);
		if ($error)
		{
			$parameters = isset($error['label']) ? [$this->language->lang($error['label'])] : [];
			throw new http_exception(400, $error['message'], $parameters);
		}

		$id = $this->request->variable('release_id', 0, false, request_interface::POST);
		$published = $id ? $this->repository->release($product['product_id'], $id, true) : false;
		$release['published_at'] = (int) ($published['published_at'] ?? 0);
		$this->template->assign_var('CL_PREVIEW', true);

		$response = $this->render_releases($product, [$release], $release['version']);
		$response->headers->set('Cache-Control', 'private, no-store');
		$response->headers->set('Referrer-Policy', 'no-referrer');
		return $response;
	}

	private function render_releases(array $product, array $releases, $version = '')
	{
		$product_links = release_notes::links($product, release_notes::PRODUCT_LINKS);
		foreach ($releases as &$release)
		{
			$published_at = (int) $release['published_at'];
			$release['date'] = $published_at ? $this->user->format_date($published_at, false, true) : '';
			$release['date_iso'] = $published_at ? gmdate('c', $published_at) : '';
			$release['sections'] = release_notes::sections($release);
			$release['links'] = $product_links ? [] : release_notes::links($release);
			$release['url'] = $version === '' ? $this->helper->route(
				'mundophpbb_changelog_release', ['slug' => $product['slug'], 'release_id' => $release['release_id']]
			) : '';
		}
		unset($release);

		$this->assign_navlinks($product);
		$id = (int) $product['product_id'];

		$this->template->assign_vars([
			'CL_PRODUCT_SUMMARY' => $this->with_summary($product, $this->repository->product_stats([$id])[$id] ?? []),
			'CL_TITLE' => $product['title'],
			'CL_INDEX_URL' => $this->helper->route('mundophpbb_changelog_index'),
			'CL_RELEASES' => $releases,
			'CL_PRODUCT_LINKS' => $product_links,
		]);

		$title = $version === '' ? $product['title'] : $product['title'] . ' ' . $version;
		return $this->helper->render('mphpbb_changelog_public.html', utf8_htmlspecialchars($title));
	}

	/**
	 * Adds the initial, published release count and latest published version to a product row.
	 */
	private function with_summary(array $product, array $stats)
	{
		$product['initial'] = mb_strtoupper(mb_substr((string) $product['title'], 0, 1, 'UTF-8'), 'UTF-8');
		$product['published'] = (int) ($stats['published'] ?? 0);
		$product['latest_version'] = (string) ($stats['latest_version'] ?? '');
		$latest = (int) ($stats['latest_at'] ?? 0);
		$product['latest_date'] = $latest ? $this->user->format_date($latest, false, true) : '';

		return $product;
	}

	private function assign_navlinks(array $product = [])
	{
		$this->template->assign_block_vars('navlinks', [
			'BREADCRUMB_NAME' => $this->language->lang('CL_NAV_LINK'),
			'U_BREADCRUMB' => $this->helper->route('mundophpbb_changelog_index'),
		]);

		if ($product)
		{
			$this->template->assign_block_vars('navlinks', [
				'BREADCRUMB_NAME' => utf8_htmlspecialchars($product['title']),
				'U_BREADCRUMB' => $this->page_url($product['slug'], 1),
			]);
		}
	}

	private function page_url($slug, $page, $filter = '')
	{
		$params = ['slug' => $slug, 'page' => $page];
		if ($filter !== '')
		{
			$params['version'] = $filter;
		}

		return $this->helper->route('mundophpbb_changelog_product', $params);
	}
}
