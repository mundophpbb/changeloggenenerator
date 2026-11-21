<?php

namespace mundophpbb\changelog\service;

class markdown_notes
{
	public const MAX_FILE_BYTES = 128 * 1024;
	public const MAX_BULK_BYTES = 512 * 1024;
	public const MAX_BULK_VERSIONS = 200;

	private const ESCAPES = [
		'\\' => '\\\\',
		'*' => '\\*',
		'_' => '\\_',
		'[' => '\\[',
		']' => '\\]',
		'<' => '\\<',
		'>' => '\\>',
		'&' => '\\&',
		'#' => '\\#',
		'~' => '\\~',
		'|' => '\\|',
		'!' => '\\!',
		'`' => '\\`',
	];

	public static function render($title, array $release, array $labels)
	{
		return self::title($title) . "\n\n" . self::render_release($release, $labels);
	}

	public static function title($title)
	{
		return '# ' . strtr($title, self::ESCAPES);
	}

	public static function render_history_release(array $release, array $labels, $date)
	{
		$markdown = self::render_release($release, $labels, $date);
		foreach (release_notes::links($release) as $link)
		{
			$markdown .= "\n[" . $labels[$link['label']] . '](<' . $link['url'] . ">)\n";
		}

		return $markdown;
	}

	private static function render_release(array $release, array $labels, $date = '')
	{
		$blocks = ['## [' . $release['version'] . ']' . ($date === '' ? '' : ' - ' . $date)];
		foreach (release_notes::sections($release) as $section)
		{
			$items = [];
			foreach ($section['items'] as $item)
			{
				$item = strtr($item, self::ESCAPES);
				$items[] = '- ' . preg_replace('/\A(\d*)([.+)-])(?=\h)/', '$1\\\\$2', $item);
			}

			$blocks[] = '### ' . $labels[$section['heading']] . "\n\n" . implode("\n", $items);
		}

		return implode("\n\n", $blocks) . "\n";
	}

	public static function for_version($markdown, $version, array $translations)
	{
		if ($markdown === '' || !preg_match('//u', $markdown))
		{
			return [];
		}

		$prefixes = [];
		$headings = [];
		foreach ($translations as $lang)
		{
			$prefixes[] = preg_quote($lang['CL_MD_VERSION_PREFIX'], '~');
			foreach ($lang['CL_MD_SECTIONS'] as $section => $aliases)
			{
				foreach ($aliases as $alias)
				{
					$headings[$alias] = $section;
				}
			}
		}

		$number = 'v?' . preg_quote(ltrim($version, 'vV'), '~');
		$version_heading = '~\A(?:(?:' . implode('|', $prefixes) . ')\h+)?'
			. '(?:\[' . $number . '\]|' . $number . ')(?=\z|\h|:|\()~iu';
		$level = 0;
		$section = 'improvements';
		$notes = [];
		$literal = array_flip(self::ESCAPES) + ['\\-' => '-', '\\+' => '+', '\\.' => '.', '\\)' => ')'];

		foreach (self::visible_lines($markdown) as $line)
		{
			if (preg_match('/^ {0,3}(#{1,6})\h+(.+?)\h*$/u', $line, $match))
			{
				$depth = strlen($match[1]);
				$title = preg_replace('/\h+#+$/', '', $match[2]);
				if (!$level)
				{
					if (preg_match($version_heading, $title))
					{
						$level = $depth;
					}
					continue;
				}
				if ($depth <= $level)
				{
					break;
				}

				$heading = mb_strtolower(trim($title, " \t:"), 'UTF-8');
				$section = $headings[$heading] ?? 'improvements';
				continue;
			}

			if ($level && preg_match('/^ {0,3}(?:[-+*]|\d+[.)])\h+(\S.*)$/u', $line, $match))
			{
				$notes[$section][] = strtr(trim($match[1]), $literal);
			}
		}

		foreach ($notes as $key => $items)
		{
			$notes[$key] = implode("\n", $items);
			if (strlen($notes[$key]) > release_notes::MAX_SECTION_BYTES)
			{
				return [];
			}
		}

		return $notes;
	}

	public static function bulk_versions($markdown, array $translations)
	{
		if ($markdown === '' || !preg_match('//u', $markdown))
		{
			return [];
		}

		$prefixes = [];
		foreach ($translations as $lang)
		{
			$prefixes[] = preg_quote($lang['CL_MD_VERSION_PREFIX'], '~');
		}
		$number = 'v?[0-9][0-9A-Za-z.+_-]*';
		$pattern = '~\A(?:(?:' . implode('|', $prefixes) . ')\h+)?'
			. '(?:\[(' . $number . ')\]|(' . $number . '))(?=\z|\h|:|\()~iu';
		$entries = [];
		$seen = [];
		foreach (self::version_blocks($markdown, $pattern) as [$version, $block])
		{
			$key = strtolower($version);
			if (isset($seen[$key]) || !zip_comparator::valid_version($version))
			{
				continue;
			}
			$seen[$key] = true;
			if (count($seen) > self::MAX_BULK_VERSIONS)
			{
				throw new \RuntimeException('CL_BULK_TOO_MANY');
			}
			$notes = self::for_version($block, $version, $translations);
			if ($notes)
			{
				$entries[$key] = ['version' => $version, 'notes' => $notes];
			}
		}

		return $entries;
	}

	private static function version_blocks($markdown, $pattern)
	{
		$level = 0;
		$version = '';
		$block = '';
		foreach (self::visible_lines($markdown) as $line)
		{
			if (preg_match('/^ {0,3}(#{1,6})\h+(.+?)\h*$/u', $line, $match))
			{
				$depth = strlen($match[1]);
				if ($level && $depth <= $level)
				{
					yield [$version, $block];
					$level = 0;
					$block = '';
				}
				if (!$level && preg_match($pattern, preg_replace('/\h+#+$/', '', $match[2]), $numbers))
				{
					$version = $numbers[1] !== '' ? $numbers[1] : $numbers[2];
					$level = $depth;
				}
			}
			if ($level)
			{
				$block .= $line . "\n";
			}
		}
		if ($level)
		{
			yield [$version, $block];
		}
	}

	private static function visible_lines($markdown)
	{
		$markdown = preg_replace('/\A\xEF\xBB\xBF/', '', $markdown);
		$fence = '';
		foreach (preg_split('/\r\n|\r|\n/', $markdown) as $line)
		{
			if (preg_match('/^ {0,3}(`{3,}|~{3,})(.*)$/', $line, $match))
			{
				if ($fence === '')
				{
					$fence = $match[1];
				}
				elseif ($match[1][0] === $fence[0] && strlen($match[1]) >= strlen($fence) && trim($match[2]) === '')
				{
					$fence = '';
				}
				continue;
			}
			if ($fence !== '')
			{
				continue;
			}

			yield $line;
		}
	}
}
