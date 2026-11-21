<?php

namespace mundophpbb\changelog\controller;

use mundophpbb\changelog\service\bulk_import;
use mundophpbb\changelog\service\exporter;
use mundophpbb\changelog\service\markdown_notes;
use mundophpbb\changelog\service\product_rules;
use mundophpbb\changelog\service\release_notes;
use mundophpbb\changelog\service\repository;
use mundophpbb\changelog\service\translations;
use mundophpbb\changelog\service\zip_comparator;
use phpbb\config\config;
use phpbb\language\language;
use phpbb\log\log_interface;
use phpbb\request\request_interface;
use phpbb\template\template;
use phpbb\user;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * ACP module entry point: routes the request, runs form operations and asks
 * admin_views to fill the template for the resulting screen.
 */
class admin_controller
{
	public const FORM = 'mundophpbb_changelog';
	private const DISPLAYED_CHANGES = 500;
	private const VIEWS = ['versions', 'import', 'compare', 'compare_notes'];

	private $request;
	private $template;
	private $language;
	private $views;
	private $repository;
	private $comparator;
	private $translations;
	private $exporter;
	private $bulk;
	private $config;
	private $user;
	private $log;

	/** @var admin_urls */
	private $urls;

	public function __construct(request_interface $request, template $template, language $language, admin_views $views,
		repository $repository, zip_comparator $comparator, translations $translations, exporter $exporter,
		bulk_import $bulk, config $config, user $user, log_interface $log)
	{
		$this->request = $request;
		$this->template = $template;
		$this->language = $language;
		$this->views = $views;
		$this->repository = $repository;
		$this->comparator = $comparator;
		$this->translations = $translations;
		$this->exporter = $exporter;
		$this->bulk = $bulk;
		$this->config = $config;
		$this->user = $user;
		$this->log = $log;
	}

	public function handle($action)
	{
		$this->language->add_lang('common', 'mundophpbb/changelog');
		add_form_key(self::FORM);

		$product_id = $this->request->variable('product', 0, false, request_interface::GET);
		$product = $product_id ? $this->repository->product($product_id) : false;
		$this->urls = new admin_urls($action, $this->request, $product ? $product_id : 0);

		$view = $this->request->variable('view', 'versions', false, request_interface::GET);
		if (!in_array($view, self::VIEWS, true))
		{
			$view = 'versions';
		}

		$release = $this->load_release($product, $view);
		if ($release)
		{
			$view = 'editor';
		}

		$this->views->assign_common($this->urls, $product, $view);
		if ($product)
		{
			$this->views->assign_product($this->urls, $product, $view);
			if ($release)
			{
				$this->views->assign_editor($this->urls, $product, $release, $this->locale());
			}
		}
	}

	/**
	 * Runs the requested operation, if any, and returns the release to open in the editor.
	 */
	private function load_release($product, &$view)
	{
		if ($this->request->is_set_post('operation'))
		{
			if ($product && $this->post('operation') === 'delete_release')
			{
				return $this->delete_release($product);
			}
			if (!check_form_key(self::FORM))
			{
				$this->error('FORM_INVALID');
				return null;
			}

			return $this->handle_post($product);
		}
		if ($this->request->server('REQUEST_METHOD') === 'POST')
		{
			// A POST without an operation means the upload exceeded post_max_size.
			$this->error('CL_ERR_UPLOAD');
			return null;
		}
		if (!$product)
		{
			if ($this->request->variable('product', 0, false, request_interface::GET))
			{
				$this->error('CL_ERR_PRODUCT_MISSING');
			}
			return null;
		}

		$product_id = (int) $product['product_id'];
		if ($this->request->is_set('history', request_interface::GET))
		{
			$current = $this->repository->release($product_id, $this->request->variable('history', 0, false, request_interface::GET));
			if (!$current)
			{
				$this->error('CL_ERR_RELEASE_MISSING');
				return null;
			}

			$view = 'history';
			$this->views->assign_history($this->urls, $product_id, $current);
			return null;
		}
		if ($this->request->is_set('release', request_interface::GET))
		{
			$id = $this->request->variable('release', 0, false, request_interface::GET);
			$release = $id ? $this->repository->release($product_id, $id) : release_notes::blank();
			if (!$release)
			{
				$this->error('CL_ERR_RELEASE_MISSING');
				return null;
			}
			if ($id && $this->request->is_set('restore', request_interface::GET))
			{
				return $this->load_history($release, $this->request->variable('restore', 0, false, request_interface::GET));
			}

			return $release;
		}
		if ($this->request->is_set('duplicate', request_interface::GET))
		{
			return $this->duplicate_release($product_id, $this->request->variable('duplicate', 0, false, request_interface::GET));
		}

		return null;
	}

	private function handle_post($product)
	{
		$operation = $this->post('operation');
		if ($operation === 'save_navigation')
		{
			$enabled = $this->request->variable('show_navigation', false, false, request_interface::POST);
			$this->config->set('mphpbb_cl_show_navigation', (int) $enabled);
			redirect($this->urls->url($product['product_id'] ?? 0, null, 1, 'navigation'));
			return null;
		}
		if ($operation === 'create_product')
		{
			$this->create_product();
			return null;
		}
		if (!$product)
		{
			$this->error('CL_ERR_PRODUCT_MISSING');
			return null;
		}

		switch ($operation)
		{
			case 'rename_product':
				$this->update_product($product);
				return null;

			case 'export_history':
			case 'export_history_bbcode':
				$this->export_history($product, $operation === 'export_history_bbcode');
				return null;

			case 'import_markdown':
				return $this->import_markdown($product);

			case 'preview_bulk':
			case 'import_bulk':
				$this->bulk_markdown($product, $operation === 'import_bulk');
				return null;

			case 'compare':
				return $this->compare($product);

			case 'save':
			case 'publish':
			case 'unpublish':
			case 'export_bbcode':
			case 'export_markdown':
				return $this->submit_release($product, $operation);
		}

		$this->error('CL_ERR_SAVE');
		return null;
	}

	private function create_product()
	{
		$data = [
			'title' => $this->post('title'),
			'slug' => strtolower($this->post('slug')),
			'download_url' => $this->post('product_download_url'),
			'support_url' => $this->post('product_support_url'),
		];
		$this->template->assign_vars([
			'CL_NEW_TITLE' => $data['title'],
			'CL_NEW_SLUG' => $data['slug'],
			'CL_NEW_PRODUCT_DOWNLOAD' => $data['download_url'],
			'CL_NEW_PRODUCT_SUPPORT' => $data['support_url'],
		]);

		$error = product_rules::error($data['title'], $data['download_url'], $data['support_url'], $data['slug']);
		if ($error)
		{
			$this->error($error);
			return;
		}

		$id = $this->repository->add_product($data);
		if (!$id)
		{
			$this->error($this->repository->product_by_slug($data['slug']) ? 'CL_ERR_SLUG_TAKEN' : 'CL_ERR_SAVE');
			return;
		}

		redirect($this->urls->url($id));
	}

	private function update_product(array $product)
	{
		$data = [
			'title' => $this->post('title'),
			'download_url' => $this->request->is_set_post('product_download_url')
				? $this->post('product_download_url') : (string) $product['download_url'],
			'support_url' => $this->request->is_set_post('product_support_url')
				? $this->post('product_support_url') : (string) $product['support_url'],
		];
		$this->template->assign_vars([
			'CL_EDIT_PRODUCT_TITLE' => $data['title'],
			'CL_EDIT_PRODUCT_DOWNLOAD' => $data['download_url'],
			'CL_EDIT_PRODUCT_SUPPORT' => $data['support_url'],
		]);

		$error = product_rules::error($data['title'], $data['download_url'], $data['support_url']);
		if ($error)
		{
			$this->error($error);
		}
		elseif (!$this->repository->update_product((int) $product['product_id'], $data))
		{
			$this->error('CL_ERR_SAVE');
		}
		else
		{
			redirect($this->urls->url($product['product_id']));
		}
	}

	private function import_markdown(array $product)
	{
		$version = $this->post('import_version');
		$this->template->assign_var('CL_IMPORT_VERSION', mb_substr($version, 0, 64, 'UTF-8'));
		if (!zip_comparator::valid_version($version))
		{
			$this->error('CL_ERR_VERSION');
			return null;
		}

		try
		{
			$markdown = bulk_import::read_upload(
				$this->request->file('changelog_file'), markdown_notes::MAX_FILE_BYTES, 'CL_ERR_MARKDOWN_UPLOAD'
			);
		}
		catch (RuntimeException $exception)
		{
			$this->error($exception->getMessage());
			return null;
		}

		$notes = markdown_notes::for_version($markdown, $version, $this->translations->all());
		if (!$notes)
		{
			$this->error('CL_ERR_MARKDOWN_NOTES');
			return null;
		}

		$existing = $this->repository->release_for_version($product['product_id'], $version);
		$release = $existing ?: release_notes::blank();
		$release['version'] = $existing ? $existing['version'] : $version;
		$this->template->assign_var('CL_NOTICE', $this->language->lang('CL_MARKDOWN_EDITOR_NOTICE'));

		return array_replace($release, array_fill_keys(array_keys(release_notes::SECTIONS), ''), $notes);
	}

	private function bulk_markdown(array $product, $confirm)
	{
		$product_id = (int) $product['product_id'];
		$secret = $this->user->data['user_form_salt'];
		$session = $this->user->session_id;
		$this->template->assign_var('CL_BULK_OPEN', true);

		try
		{
			$markdown = $confirm
				? bulk_import::decode_payload(
					$this->request->variable('bulk_payload', '', false, request_interface::POST),
					$this->request->variable('bulk_expires', 0, false, request_interface::POST),
					$this->request->variable('bulk_signature', '', false, request_interface::POST),
					$secret, $session, $product_id
				)
				: bulk_import::read_upload($this->request->file('bulk_file'), markdown_notes::MAX_BULK_BYTES, 'CL_BULK_INVALID_FILE');
			$entries = markdown_notes::bulk_versions($markdown, $this->translations->all());
		}
		catch (RuntimeException $exception)
		{
			$this->error($exception->getMessage());
			return;
		}
		if (!$entries)
		{
			$this->error('CL_BULK_NO_NOTES');
			return;
		}

		if ($confirm)
		{
			$selection = $this->request->variable('bulk_versions', [''], false, request_interface::POST);
			$selected = array_intersect_key($entries, array_fill_keys($selection, true));
			if ($selected)
			{
				$result = $this->bulk->import_drafts($product_id, $selected);
				$this->template->assign_var('CL_NOTICE', $this->language->lang('CL_BULK_DONE', $result['saved'], $result['skipped']));
				if ($result['failed'])
				{
					$this->template->assign_var('CL_ERROR', $this->language->lang('CL_BULK_SAVE_FAILED', $result['failed']));
				}
				return;
			}
			$this->error('CL_BULK_SELECT_VERSIONS');
		}

		$preview = $this->bulk->preview($product_id, $entries);
		$payload = base64_encode($markdown);
		$expires = time() + bulk_import::PREVIEW_LIFETIME;
		$this->template->assign_vars([
			'CL_BULK_ENTRIES' => $preview['rows'],
			'CL_BULK_CAN_CONFIRM' => count($preview['rows']) > $preview['existing'],
			'CL_BULK_PAYLOAD' => $payload,
			'CL_BULK_EXPIRES' => $expires,
			'CL_BULK_SIGNATURE' => bulk_import::signature($secret, $session, $product_id, $expires, $payload),
		]);
	}

	private function compare(array $product)
	{
		try
		{
			$comparison = $this->comparator->compare($this->request->file('old_zip'), $this->request->file('new_zip'));
		}
		catch (RuntimeException $exception)
		{
			$this->error($exception->getMessage());
			return null;
		}

		$counts = ['added' => 0, 'changed' => 0, 'removed' => 0];
		$paths = [];
		foreach ($comparison['changes'] as $path => $change)
		{
			$counts[$change]++;
			if (count($paths) < self::DISPLAYED_CHANGES)
			{
				$paths[] = ['name' => $path, 'status' => 'CL_FILE_' . strtoupper($change)];
			}
		}

		$this->template->assign_vars([
			'CL_COMPARE' => $comparison['package'],
			'CL_COMPARE_OLD' => $comparison['old_version'],
			'CL_COMPARE_NEW' => $comparison['new_version'],
			'CL_COUNTS' => $counts,
			'CL_PATHS' => $paths,
			'CL_PATH_LIMIT' => array_sum($counts) > self::DISPLAYED_CHANGES
				? $this->language->lang('CL_PATH_LIMIT', self::DISPLAYED_CHANGES) : '',
		]);

		$existing = $this->repository->release_for_version($product['product_id'], $comparison['new_version']);
		if ($existing)
		{
			$this->template->assign_var('CL_NOTICE', $this->language->lang('CL_EXISTING'));
			return $existing;
		}

		$release = release_notes::blank();
		$release['version'] = $comparison['new_version'];
		$release['previous_version'] = $comparison['old_version'];

		$imported = markdown_notes::for_version($comparison['changelog'], $release['version'], $this->translations->all());
		if ($imported)
		{
			$this->template->assign_var('CL_NOTICE', $this->language->lang('CL_CHANGELOG_IMPORTED'));
			return array_replace($release, $imported);
		}

		$lines = [];
		foreach (zip_comparator::areas($comparison['changes']) as $change => $areas)
		{
			foreach ($areas as $area)
			{
				$lines[] = $this->language->lang('CL_SUGGEST_' . strtoupper($change), $this->language->lang($area));
			}
		}
		$release['improvements'] = implode("\n", $lines);

		return $release;
	}

	private function submit_release(array $product, $operation)
	{
		$id = $this->request->variable('release_id', 0, false, request_interface::POST);
		$revision = $this->request->variable('revision', 0, false, request_interface::POST);
		$existing = $id ? $this->repository->release($product['product_id'], $id) : false;
		$data = release_notes::from_editor($this->request);
		$release = array_replace($existing ?: release_notes::blank(), ['revision' => $revision], $data);

		if ($id && !$existing)
		{
			$this->error('CL_ERR_RELEASE_MISSING');
			return null;
		}
		$export = $operation === 'export_bbcode' || $operation === 'export_markdown';
		if (!$export && $existing && (int) $existing['revision'] !== $revision)
		{
			$this->set_conflict($product, $id);
			return $release;
		}
		$error = release_notes::editor_error($data);
		if ($error)
		{
			if (isset($error['field']))
			{
				$this->template->assign_var('CL_LINK_ERROR',
					$this->language->lang($error['message'], $this->language->lang($error['label'])));
			}
			else
			{
				$this->error($error['message']);
			}
			return $release;
		}

		if ($export)
		{
			$labels = $this->translations->labels()[$this->locale()];
			$this->send($this->exporter->release($product, $release, $labels, $operation === 'export_markdown'));
			return null;
		}

		$published_at = (int) $release['published_at'];
		if ($operation === 'unpublish')
		{
			$published_at = 0;
		}
		elseif ($operation === 'publish' && !$published_at)
		{
			$published_at = time();
		}
		if ($published_at && !release_notes::sections($release))
		{
			$this->error('CL_ERR_EMPTY_RELEASE');
			return $release;
		}

		$data['published_at'] = $published_at;
		$saved = $this->repository->save_release($data, (int) $product['product_id'], $existing, [
			'id' => (int) $this->user->data['user_id'],
			'name' => $this->user->data['username'],
		]);
		if ($saved)
		{
			redirect($this->urls->url($product['product_id'], $saved, $this->urls->page(), $operation));
			return null;
		}

		$duplicate = $this->repository->release_for_version($product['product_id'], $data['version']);
		if ($duplicate && (int) $duplicate['release_id'] !== $id)
		{
			$this->error('CL_ERR_DUPLICATE_VERSION');
		}
		elseif ($existing && (int) ($this->repository->release($product['product_id'], $id)['revision'] ?? 0) !== $revision)
		{
			$this->set_conflict($product, $id);
		}
		else
		{
			$this->error('CL_ERR_SAVE');
		}
		return $release;
	}

	/**
	 * Deletes a saved release after the standard phpBB confirmation page.
	 * The confirmation is protected by confirm_box()'s own key; the first
	 * request comes from the editor and carries the module's form key.
	 */
	private function delete_release(array $product)
	{
		$id = $this->request->variable('release_id', 0, false, request_interface::POST);
		$release = $id ? $this->repository->release($product['product_id'], $id) : false;
		if (!$release)
		{
			$this->error('CL_ERR_RELEASE_MISSING');
			return null;
		}
		if ($this->request->is_set_post('cancel'))
		{
			return $release;
		}

		if (confirm_box(true))
		{
			if (!$this->repository->delete_release($product['product_id'], $id))
			{
				$this->error('CL_ERR_DELETE');
				return $release;
			}

			$this->log->add('admin', (int) $this->user->data['user_id'], $this->user->ip, 'LOG_CL_RELEASE_DELETED', false,
				[$product['title'], $release['version']]);
			redirect($this->urls->url($product['product_id'], null, $this->urls->page(), 'deleted'));
			return null;
		}
		if ($this->request->is_set_post('confirm') || !check_form_key(self::FORM))
		{
			$this->error('FORM_INVALID');
			return $release;
		}

		confirm_box(false, ['CL_DELETE_RELEASE', $release['version']], build_hidden_fields([
			'operation' => 'delete_release',
			'release_id' => $id,
		]));
		return $release;
	}

	private function export_history(array $product, $bbcode)
	{
		$from_value = $this->post('export_from');
		$through_value = $this->post('export_through');
		$locale = $this->locale();
		$this->template->assign_vars([
			'CL_HISTORY_EXPORT_OPEN' => true,
			'CL_HISTORY_SELECTED_LOCALE' => $locale,
			'CL_HISTORY_EXPORT_FROM' => $from_value,
			'CL_HISTORY_EXPORT_THROUGH' => $through_value,
		]);

		$from = exporter::parse_date($from_value);
		$through = exporter::parse_date($through_value);
		if (($from_value !== '' && !$from) || ($through_value !== '' && !$through))
		{
			$this->error('CL_EXPORT_HISTORY_BAD_DATE');
			return;
		}
		if ($from && $through && $from > $through)
		{
			$this->error('CL_EXPORT_HISTORY_DATE_ORDER');
			return;
		}

		$entries = $this->repository->published_history(
			$product['product_id'],
			$from ? $from->getTimestamp() : null,
			$through ? $through->setTime(23, 59, 59)->getTimestamp() : null
		);
		if (!$entries->valid())
		{
			$this->error($from || $through ? 'CL_EXPORT_HISTORY_NO_DATES' : 'CL_EXPORT_HISTORY_EMPTY');
			return;
		}

		$labels = $this->translations->labels()[$locale];
		$this->send($this->exporter->history($product, $entries, $labels, $bbcode));
	}

	private function send(Response $response)
	{
		$response->sendHeaders();
		$response->sendContent();
		garbage_collection();
		exit_handler();
	}

	private function set_conflict(array $product, $id)
	{
		$this->error('CL_ERR_CONFLICT');
		$this->template->assign_var('CL_RELOAD_URL', $this->urls->url($product['product_id'], $id, $this->urls->page()));
	}

	private function load_history(array $release, $history_id)
	{
		$entry = $this->repository->history_entry($release['release_id'], $history_id);
		if (!$entry)
		{
			$this->error('CL_ERR_HISTORY_MISSING');
			return $release;
		}

		$this->template->assign_var('CL_NOTICE', $this->language->lang('CL_HISTORY_REVIEW_NOTICE'));
		return array_replace($release, array_intersect_key($entry, release_notes::SECTIONS + release_notes::LINKS));
	}

	private function duplicate_release($product_id, $source_id)
	{
		$source = $this->repository->release($product_id, $source_id);
		if (!$source)
		{
			$this->error('CL_ERR_RELEASE_MISSING');
			return null;
		}

		$this->template->assign_var('CL_NOTICE', $this->language->lang('CL_DUPLICATE_DRAFT_NOTICE', $source['version']));

		$notes = array_intersect_key($source, release_notes::SECTIONS + ['support_url' => true]);
		return $notes + ['previous_version' => $source['version']] + release_notes::blank();
	}

	/**
	 * Language chosen for an export or the BBCode preview, defaulting to the admin's own.
	 */
	private function locale()
	{
		return $this->translations->resolve($this->post('export_locale'), $this->language->lang('CL_LOCALE'));
	}

	private function post($field)
	{
		$text = $this->request->variable($field, '', true, request_interface::POST);
		return trim(str_replace(["\r\n", "\r"], "\n", htmlspecialchars_decode($text, ENT_QUOTES)));
	}

	private function error($key)
	{
		$this->template->assign_var('CL_ERROR', $this->language->lang($key));
	}
}
