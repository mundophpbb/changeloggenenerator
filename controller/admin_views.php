<?php

namespace mundophpbb\changelog\controller;

use mundophpbb\changelog\service\markdown_notes;
use mundophpbb\changelog\service\release_comparison;
use mundophpbb\changelog\service\release_notes;
use mundophpbb\changelog\service\repository;
use mundophpbb\changelog\service\translations;
use mundophpbb\changelog\service\zip_comparator;
use phpbb\config\config;
use phpbb\controller\helper;
use phpbb\language\language;
use phpbb\request\request_interface;
use phpbb\template\template;
use phpbb\user;

/**
 * Fills the ACP template for each screen of the module (read-only work).
 * State-changing operations live in admin_controller.
 */
class admin_views
{
	public const PAGE_SIZE = 15;

	private $request;
	private $template;
	private $language;
	private $helper;
	private $repository;
	private $translations;
	private $config;
	private $user;

	public function __construct(request_interface $request, template $template, language $language, helper $helper,
		repository $repository, translations $translations, config $config, user $user)
	{
		$this->request = $request;
		$this->template = $template;
		$this->language = $language;
		$this->helper = $helper;
		$this->repository = $repository;
		$this->translations = $translations;
		$this->config = $config;
		$this->user = $user;
	}

	/**
	 * Values every screen needs: navigation, product list and language selectors.
	 */
	public function assign_common(admin_urls $urls, $product, $view)
	{
		$product_id = $product ? (int) $product['product_id'] : 0;
		$settings_saved = $this->request->variable('notice', '', false, request_interface::GET) === 'navigation';
		if ($settings_saved && !$this->request->is_set_post('operation'))
		{
			$this->template->assign_var('CL_NOTICE', $this->language->lang('CL_NAV_SAVED'));
		}
		if (!$product && $this->request->variable('notice', '', false, request_interface::GET) === 'product_deleted'
			&& !$this->request->is_set_post('operation'))
		{
			$this->template->assign_var('CL_NOTICE', $this->language->lang('CL_PRODUCT_DELETED'));
		}

		$products = $product ? [] : $this->repository->products();
		$stats = $products ? $this->repository->product_stats() : [];
		foreach ($products as &$option)
		{
			$option = $this->with_stats($option, $stats[(int) $option['product_id']] ?? []);
			$option['url'] = $urls->url($option['product_id']);
		}
		unset($option);

		$this->template->assign_vars([
			'CL_BASE_URL' => $urls->base(),
			'CL_ACTION' => $urls->url($product_id),
			'CL_VIEW' => $view,
			'CL_PRODUCTS' => $products,
			'CL_PRODUCT' => $product,
			'CL_NAV_ENABLED' => !empty($this->config['mphpbb_cl_show_navigation']),
			'CL_NAV_SETTINGS_OPEN' => $settings_saved,
			'CL_PRODUCT_LINK_LIMIT' => release_notes::MAX_URL_LENGTH,
			'CL_INDEX_URL' => $this->helper->route('mundophpbb_changelog_index'),
			'CL_LOCALES' => $this->translations->locales(),
		]);
	}

	public function assign_product(admin_urls $urls, array $product, $view)
	{
		$id = (int) $product['product_id'];
		$page = $urls->page();
		$links = release_notes::links($product, release_notes::PRODUCT_LINKS);
		$summary = $this->with_stats($product, $this->repository->product_stats([$id])[$id] ?? []);
		$has_releases = $summary['published'] > 0;
		$this->template->assign_vars([
			'CL_PRODUCT_SUMMARY' => $summary,
			'CL_NEW_RELEASE_URL' => $urls->url($id, 0, $page),
			'CL_VERSIONS_URL' => $urls->url($id, null, $page),
			'CL_IMPORT_URL' => $urls->url($id, null, $page, '', 'import'),
			'CL_COMPARE_URL' => $urls->url($id, null, $page, '', 'compare'),
			'CL_NOTES_COMPARE_URL' => $urls->url($id, null, $page, '', 'compare_notes'),
			'CL_EDITOR_ACTION' => $urls->url($id, null, $page),
			'CL_HAS_PUBLISHED_RELEASES' => $has_releases,
			'CL_PUBLIC_URL' => $has_releases || $links ? $this->helper->route(
				'mundophpbb_changelog_product', ['slug' => $product['slug']]
			) : '',
		]);

		switch ($view)
		{
			case 'import':
				$this->template->assign_vars([
					'CL_IMPORT_LIMIT' => min(markdown_notes::MAX_FILE_BYTES, self::php_upload_limit()),
					'CL_BULK_LIMIT' => min(markdown_notes::MAX_BULK_BYTES, self::php_upload_limit()),
				]);
				return;

			case 'compare':
				$this->template->assign_var('CL_UPLOAD_LIMIT', min(zip_comparator::UPLOAD_LIMIT_MB * 1024 * 1024, self::php_upload_limit()));
				return;

			case 'compare_notes':
				$this->assign_notes_comparison($urls, $id);
				return;

			case 'versions':
				$this->assign_announcement($product, $links);
				$this->assign_release_list($urls, $id);
				return;
		}
	}

	private function assign_announcement(array $product, array $links)
	{
		if (!$links)
		{
			return;
		}

		$blocks = [release_notes::bbcode_heading($product['title'])];
		if (release_notes::valid_url((string) $product['download_url']))
		{
			$blocks[] = $this->language->lang('CL_PRODUCT_ANNOUNCEMENT_TEXT');
		}
		$buttons = [];
		foreach ($links as $link)
		{
			$buttons[] = '[url=' . $link['url'] . ']' . $this->language->lang($link['label']) . '[/url]';
		}
		$blocks[] = implode("\n", $buttons);
		$this->template->assign_var('CL_PRODUCT_ANNOUNCEMENT', implode("\n\n", $blocks));
	}

	private function assign_release_list(admin_urls $urls, $id)
	{
		$total = $urls->filter_invalid() ? 0
			: $this->repository->count_releases($id, $urls->filter_version(), $urls->filter_status());
		$last_page = max(1, (int) ceil($total / self::PAGE_SIZE));
		$page = min($urls->page(), $last_page);
		$releases = $total ? $this->repository->saved_releases(
			$id, $urls->filter_version(), $urls->filter_status(), self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE
		) : [];
		$list_url = $urls->url($id, null, $page);
		foreach ($releases as &$release)
		{
			$release['date'] = $release['published_at'] ? $this->user->format_date((int) $release['published_at'], false, true) : '';
			$release['url'] = $urls->url($id, $release['release_id'], $page);
			$release['duplicate_url'] = $list_url . '&amp;duplicate=' . (int) $release['release_id'] . '#cl-editor';
		}
		unset($release);

		if ($this->request->variable('notice', '', false, request_interface::GET) === 'deleted' && !$this->request->is_set_post('operation'))
		{
			$this->template->assign_var('CL_NOTICE', $this->language->lang('CL_RELEASE_DELETED'));
		}

		$form = admin_urls::form($urls->url($id, null, 1, '', '', false));
		$this->template->assign_vars([
			'CL_VERSIONS_URL' => $list_url,
			'CL_HISTORY_EXPORT_LOCALE' => $this->language->lang('CL_LOCALE'),
			'CL_FILTER_ACTION' => $form['action'],
			'CL_FILTER_FIELDS' => $form['fields'],
			'CL_FILTER_OPTIONS' => admin_urls::STATUS_FILTERS,
			'CL_FILTER_STATUS' => $urls->filter_status(),
			'CL_FILTER_VERSION' => $urls->filter_version(),
			'CL_FILTER_INVALID' => $urls->filter_invalid(),
			'CL_FILTER_CLEAR_URL' => $urls->filtered() ? $urls->url($id, null, 1, '', '', false) : '',
			'CL_RELEASES' => $releases,
			'CL_LIST_PREVIOUS' => $page > 1 ? $urls->url($id, null, $page - 1) . '#cl-release-list' : '',
			'CL_LIST_NEXT' => $page < $last_page ? $urls->url($id, null, $page + 1) . '#cl-release-list' : '',
		]);
	}

	private function assign_notes_comparison(admin_urls $urls, $product_id)
	{
		$form = admin_urls::form($urls->url($product_id, null, $urls->page(), '', 'compare_notes'));
		$base = $this->request->variable('base_version', '', false, request_interface::GET);
		$target = $this->request->variable('target_version', '', false, request_interface::GET);
		$this->template->assign_vars([
			'CL_NOTES_FORM' => $form,
			'CL_NOTES_BASE' => substr($base, 0, 64),
			'CL_NOTES_TARGET' => substr($target, 0, 64),
			'CL_NOTES_RESET_URL' => ($base !== '' || $target !== '') ? $urls->url($product_id, null, $urls->page(), '', 'compare_notes') : '',
			'CL_NOTES_SUGGESTIONS' => $this->repository->saved_releases($product_id, '', 'all', 50, 0),
		]);
		if ($base === '' && $target === '')
		{
			return;
		}
		if (!zip_comparator::valid_version($base) || !zip_comparator::valid_version($target))
		{
			$this->error('CL_ERR_NOTES_COMPARE_VERSIONS');
			return;
		}

		$before = $this->repository->release_for_version($product_id, $base);
		$after = $this->repository->release_for_version($product_id, $target);
		if (!$before || !$after)
		{
			$this->error('CL_ERR_NOTES_COMPARE_VERSIONS');
			return;
		}
		if ((int) $before['release_id'] === (int) $after['release_id'])
		{
			$this->error('CL_ERR_NOTES_COMPARE_SAME');
			return;
		}

		$this->template->assign_vars([
			'CL_NOTES_COMPARED' => true,
			'CL_NOTES_BEFORE' => $before['version'],
			'CL_NOTES_AFTER' => $after['version'],
			'CL_NOTES_DIFF' => release_comparison::compare($before, $after),
		]);
	}

	public function assign_history(admin_urls $urls, $product_id, array $release)
	{
		$id = (int) $release['release_id'];
		$list_page = $urls->page();
		$page = max(1, min(100000, $this->request->variable('history_page', 1, false, request_interface::GET)));
		$entries = $this->repository->release_history($id, self::PAGE_SIZE + 1, ($page - 1) * self::PAGE_SIZE);
		$has_next = count($entries) > self::PAGE_SIZE;
		if ($has_next)
		{
			array_pop($entries);
		}

		$base = $urls->url($product_id, null, $list_page);
		foreach ($entries as &$entry)
		{
			$entry['date'] = $this->user->format_date((int) $entry['changed_at'], false, true);
			$entry['restore_url'] = $base . '&amp;release=' . $id . '&amp;restore=' . (int) $entry['history_id'] . '#cl-editor';
		}
		unset($entry);

		$history_url = $base . '&amp;history=' . $id;
		$this->template->assign_vars([
			'CL_HISTORY_VERSION' => $release['version'],
			'CL_HISTORY_ENTRIES' => $entries,
			'CL_HISTORY_EDITOR_URL' => $urls->url($product_id, $id, $list_page),
			'CL_HISTORY_PREVIOUS' => $page > 1 ? $history_url . '&amp;history_page=' . ($page - 1) : '',
			'CL_HISTORY_NEXT' => $has_next ? $history_url . '&amp;history_page=' . ($page + 1) : '',
		]);
	}

	public function assign_editor(admin_urls $urls, array $product, array $release, $locale)
	{
		$labels = $this->translations->labels();
		$sections = [];
		foreach (release_notes::SECTIONS as $key => $heading)
		{
			$sections[] = ['field' => $key, 'heading' => $heading, 'notes' => $release[$key]];
		}
		$links = [];
		foreach (release_notes::LINKS as $field => $label)
		{
			$links[] = ['field' => $field, 'label' => $label, 'url' => $release[$field] ?? ''];
		}

		$notices = ['save' => 'CL_SAVED', 'publish' => 'CL_PUBLISHED_NOTICE', 'unpublish' => 'CL_UNPUBLISHED_NOTICE'];
		$notice = $this->request->variable('notice', '', false, request_interface::GET);
		if ($release['release_id'] && isset($notices[$notice]))
		{
			$this->template->assign_var('CL_NOTICE', $this->language->lang($notices[$notice]));
		}

		$this->template->assign_vars([
			'CL_EDITOR' => $release,
			'CL_EDITOR_HISTORY_URL' => $release['release_id'] ? $urls->url($product['product_id'], null, $urls->page())
				. '&amp;history=' . (int) $release['release_id'] : '',
			'CL_EDITOR_PREVIEW_URL' => $this->helper->route('mundophpbb_changelog_preview', ['slug' => $product['slug']]),
			'CL_EDITOR_PUBLIC_URL' => $release['published_at'] ? $this->helper->route(
				'mundophpbb_changelog_release', ['slug' => $product['slug'], 'release_id' => $release['release_id']]
			) : '',
			'CL_NOTES_MAX_LENGTH' => release_notes::MAX_SECTION_BYTES,
			'CL_EDITOR_SECTIONS' => $sections,
			'CL_EDITOR_LINKS' => $links,
			'CL_EDITOR_LINK_MAX' => release_notes::MAX_URL_LENGTH,
			'CL_BB_LOCALE' => $locale,
			'CL_BB_LABELS' => json_encode($labels, JSON_UNESCAPED_UNICODE),
			'CL_BBCODE' => $release['version'] === '' ? '' : release_notes::bbcode($product['title'], $release, $labels[$locale]),
		]);
	}

	/**
	 * Adds release counts, the latest published version and public state to a product row.
	 */
	private function with_stats(array $product, array $stats)
	{
		$stats += ['published' => 0, 'drafts' => 0, 'latest_version' => '', 'latest_at' => 0];
		$product += $stats;
		$product['initial'] = mb_strtoupper(mb_substr((string) $product['title'], 0, 1, 'UTF-8'), 'UTF-8');
		$product['latest_date'] = $stats['latest_at'] ? $this->user->format_date($stats['latest_at'], false, true) : '';
		$product['is_public'] = $stats['published'] > 0
			|| (string) ($product['download_url'] ?? '') !== '' || (string) ($product['support_url'] ?? '') !== '';

		return $product;
	}

	private function error($key)
	{
		$this->template->assign_var('CL_ERROR', $this->language->lang($key));
	}

	/**
	 * upload_max_filesize in bytes, falling back to the ZIP limit when it cannot be read.
	 */
	public static function php_upload_limit()
	{
		$value = trim((string) ini_get('upload_max_filesize'));
		$units = ['g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024];
		$bytes = (int) $value * ($units[strtolower(substr($value, -1))] ?? 1);
		return $bytes > 0 ? $bytes : zip_comparator::UPLOAD_LIMIT_MB * 1024 * 1024;
	}
}
