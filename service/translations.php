<?php

namespace mundophpbb\changelog\service;

use phpbb\extension\manager;
use phpbb\language\language_file_loader;

/**
 * Loads the extension's "common" language file for every language it ships,
 * so imports, exports and the BBCode preview work with any translation that
 * is added under language/<iso>/ without code changes.
 */
class translations
{
	public const EXTENSION = 'mundophpbb/changelog';

	private $loader;
	private $manager;
	private $translations;

	public function __construct(language_file_loader $loader, manager $manager)
	{
		$this->loader = $loader;
		$this->manager = $manager;
	}

	/**
	 * @return array<string, array> Language arrays keyed by ISO code (e.g. en, pt_br).
	 */
	public function all()
	{
		if ($this->translations === null)
		{
			$this->translations = [];
			$root = $this->manager->get_extension_path(self::EXTENSION, true) . 'language/';
			foreach (self::available_isos($root) as $iso)
			{
				$lang = [];
				$this->loader->load_extension(self::EXTENSION, 'common', $iso, $lang);
				if ($lang)
				{
					$this->translations[$iso] = $lang;
				}
			}
		}

		return $this->translations;
	}

	/**
	 * Section and link labels for each language, as used by BBCode and Markdown exports.
	 */
	public function labels()
	{
		$keys = array_flip(release_notes::SECTIONS + release_notes::LINKS);
		return array_map(static function (array $lang) use ($keys) {
			return array_intersect_key($lang, $keys);
		}, $this->all());
	}

	/**
	 * @return array<int, array{iso: string, name: string}> Options for language selectors.
	 */
	public function locales()
	{
		$locales = [];
		foreach ($this->all() as $iso => $lang)
		{
			$locales[] = ['iso' => $iso, 'name' => $lang['CL_LANGUAGE_NAME'] ?? $iso];
		}

		return $locales;
	}

	/**
	 * Returns $requested when it is a shipped language, otherwise $default,
	 * otherwise the first shipped language.
	 */
	public function resolve($requested, $default)
	{
		$all = $this->all();
		foreach ([$requested, $default] as $iso)
		{
			if (is_string($iso) && isset($all[$iso]))
			{
				return $iso;
			}
		}

		return (string) array_key_first($all);
	}

	/**
	 * Language directories that contain a common.php file, English first.
	 */
	public static function available_isos($root)
	{
		$isos = [];
		foreach (glob(rtrim($root, '/') . '/*/common.php') ?: [] as $file)
		{
			$iso = basename(dirname($file));
			if (preg_match('/\A[a-z]{2,3}(?:_[a-z0-9]{2,8})*\z/i', $iso))
			{
				$isos[] = $iso;
			}
		}
		sort($isos, SORT_STRING);
		$english = array_search('en', $isos, true);
		if ($english !== false)
		{
			array_splice($isos, $english, 1);
			array_unshift($isos, 'en');
		}

		return $isos;
	}
}
