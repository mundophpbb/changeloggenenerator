<?php

namespace mundophpbb\changelog\service;

use phpbb\request\request_interface;

class release_notes
{
	public const MAX_SECTION_BYTES = 12000;
	public const MAX_URL_LENGTH = 2048;
	public const PRODUCT_LINKS = [
		'download_url' => 'CL_PRODUCT_DOWNLOAD_OPEN',
		'support_url' => 'CL_PRODUCT_SUPPORT_OPEN',
	];
	public const LINKS = [
		'download_url' => 'CL_LINK_DOWNLOAD',
		'support_url' => 'CL_LINK_SUPPORT',
	];

	public const SECTIONS = [
		'features' => 'CL_FEATURES',
		'improvements' => 'CL_IMPROVEMENTS',
		'fixes' => 'CL_FIXES',
	];

	/**
	 * Empty editor state for a new release.
	 */
	public static function blank()
	{
		return array_fill_keys(array_keys(self::SECTIONS + self::LINKS), '') + [
			'release_id' => 0,
			'revision' => 0,
			'version' => '',
			'previous_version' => '',
			'published_at' => 0,
		];
	}

	public static function lines($text)
	{
		return array_values(array_filter(array_map('trim', explode("\n", $text)), static function ($line) {
			return $line !== '';
		}));
	}

	public static function sections(array $release)
	{
		$sections = [];
		foreach (self::SECTIONS as $key => $heading)
		{
			$items = self::lines($release[$key]);
			if ($items)
			{
				$sections[] = ['heading' => $heading, 'items' => $items];
			}
		}

		return $sections;
	}

	public static function from_editor(request_interface $request)
	{
		$data = [];
		foreach (array_merge(['version', 'previous_version'], array_keys(self::SECTIONS + self::LINKS)) as $field)
		{
			$value = $request->variable($field, '', true, request_interface::POST);
			$data[$field] = trim(str_replace(["\r\n", "\r"], "\n", htmlspecialchars_decode($value, ENT_QUOTES)));
		}
		foreach (array_keys(self::SECTIONS) as $field)
		{
			$data[$field] = implode("\n", self::lines($data[$field]));
		}

		return $data;
	}

	public static function editor_error(array $data)
	{
		if (!zip_comparator::valid_version($data['version'])
			|| ($data['previous_version'] !== '' && !zip_comparator::valid_version($data['previous_version'])))
		{
			return ['message' => 'CL_ERR_VERSION'];
		}

		foreach (array_keys(self::SECTIONS) as $field)
		{
			if (strlen($data[$field]) > self::MAX_SECTION_BYTES)
			{
				return ['message' => 'CL_ERR_NOTES_LENGTH'];
			}
		}

		foreach (self::LINKS as $field => $label)
		{
			if ($data[$field] !== '' && !self::valid_url($data[$field]))
			{
				return ['message' => 'CL_ERR_RELEASE_URL', 'field' => $field, 'label' => $label];
			}
		}

		return null;
	}

	public static function valid_url($url)
	{
		if (strlen($url) > self::MAX_URL_LENGTH || !preg_match('%\Ahttps?://[!-~]+\z%i', $url)
			|| strpbrk($url, "[]<>\"'\\`") !== false || !filter_var($url, FILTER_VALIDATE_URL))
		{
			return false;
		}

		$parts = parse_url($url);
		return $parts !== false && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass']);
	}

	public static function links(array $release, array $labels = self::LINKS)
	{
		$links = [];
		foreach ($labels as $field => $label)
		{
			$url = $release[$field] ?? '';
			if ($url !== '' && self::valid_url($url))
			{
				$links[] = ['label' => $label, 'url' => $url];
			}
		}

		return $links;
	}

	public static function bbcode($title, array $release, array $labels)
	{
		return self::render_bbcode($title . ' ' . $release['version'], $release, $labels);
	}

	public static function bbcode_heading($text)
	{
		return '[b]' . self::bbcode_text($text) . '[/b]';
	}

	public static function bbcode_history_release(array $release, array $labels, $date)
	{
		return self::render_bbcode($release['version'] . ' · ' . $date, $release, $labels) . "\n";
	}

	private static function render_bbcode($heading, array $release, array $labels)
	{
		$blocks = [self::bbcode_heading($heading)];
		foreach (self::sections($release) as $section)
		{
			$lines = ['[b]' . $labels[$section['heading']] . '[/b]', '[list]'];
			foreach ($section['items'] as $item)
			{
				$lines[] = '[*]' . self::bbcode_text($item);
			}
			$lines[] = '[/list]';
			$blocks[] = implode("\n", $lines);
		}

		$links = [];
		foreach (self::links($release) as $link)
		{
			$links[] = '[url=' . $link['url'] . ']' . $labels[$link['label']] . '[/url]';
		}
		if ($links)
		{
			$blocks[] = implode("\n", $links);
		}

		return implode("\n\n", $blocks);
	}

	private static function bbcode_text($text)
	{
		return strtr($text, ['[' => '［', ']' => '］']);
	}
}
