<?php

namespace mundophpbb\changelog\service;

use RuntimeException;
use ZipArchive;

class zip_comparator
{
	public const UPLOAD_LIMIT_MB = 25;
	private const MAX_ENTRIES = 8000;
	private const MAX_UNCOMPRESSED = 128 * 1024 * 1024;
	private const MAX_MANIFEST = 128 * 1024;

	public static function valid_version($version)
	{
		return is_string($version) && strlen($version) <= 64
			&& preg_match('/\Av?[0-9][0-9A-Za-z.+_-]*\z/i', $version);
	}

	public const AREAS = [
		'acp' => 'CL_AREA_ACP',
		'adm' => 'CL_AREA_ACP',
		'controller' => 'CL_AREA_CONTROLLER',
		'event' => 'CL_AREA_EVENT',
		'language' => 'CL_AREA_LANGUAGE',
		'migrations' => 'CL_AREA_MIGRATIONS',
		'styles' => 'CL_AREA_STYLES',
		'ucp' => 'CL_AREA_UCP',
	];

	/**
	 * Compares two uploaded extension ZIPs (entries of $_FILES as returned by request::file()).
	 */
	public function compare(array $old_upload, array $new_upload)
	{
		return $this->compare_files($this->upload_path($old_upload), $this->upload_path($new_upload));
	}

	/**
	 * Compares two extension ZIP files on disk.
	 *
	 * @throws RuntimeException With a language key when a package is invalid or the versions do not match.
	 */
	public function compare_files($old_path, $new_path)
	{
		$old = $this->inspect($old_path, false);
		$new = $this->inspect($new_path, true);
		if ($old['name'] !== $new['name'])
		{
			throw new RuntimeException('CL_ERR_PACKAGE_MISMATCH');
		}
		if (!version_compare(ltrim($new['version'], 'vV'), ltrim($old['version'], 'vV'), '>'))
		{
			throw new RuntimeException('CL_ERR_VERSION_ORDER');
		}

		$changes = [];
		foreach (array_diff_key($new['files'], $old['files']) as $path => $hash)
		{
			$changes[$path] = 'added';
		}
		foreach (array_intersect_key($new['files'], $old['files']) as $path => $hash)
		{
			if ($hash !== $old['files'][$path])
			{
				$changes[$path] = 'changed';
			}
		}
		foreach (array_diff_key($old['files'], $new['files']) as $path => $hash)
		{
			$changes[$path] = 'removed';
		}
		ksort($changes, SORT_STRING);

		return [
			'package' => $new['name'],
			'old_version' => $old['version'],
			'new_version' => $new['version'],
			'changes' => $changes,
			'changelog' => $new['changelog'],
		];
	}

	/**
	 * Groups changed paths by extension area, e.g. ['added' => ['CL_AREA_LANGUAGE']].
	 *
	 * @param array<string, string> $changes Path => added|changed|removed
	 */
	public static function areas(array $changes)
	{
		$areas = [];
		foreach ($changes as $path => $change)
		{
			$area = self::AREAS[strtok((string) $path, '/')] ?? '';
			if ($area !== '')
			{
				$areas[$change][$area] = true;
			}
		}

		return array_map('array_keys', $areas);
	}

	private function upload_path(array $upload)
	{
		$error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
		$path = $upload['tmp_name'] ?? '';
		if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
			|| (int) ($upload['size'] ?? 0) > self::UPLOAD_LIMIT_MB * 1024 * 1024)
		{
			throw new RuntimeException('CL_ERR_ZIP_SIZE');
		}
		if ($error !== UPLOAD_ERR_OK || !is_string($path) || !is_uploaded_file($path))
		{
			throw new RuntimeException('CL_ERR_UPLOAD');
		}

		return $path;
	}

	private function inspect($path, $read_changelog)
	{
		$zip = new ZipArchive();
		if (@$zip->open($path, ZipArchive::CHECKCONS) !== true)
		{
			throw new RuntimeException('CL_ERR_ZIP_FORMAT');
		}

		try
		{
			$entries = $this->entries($zip);
			[$root, $name, $version] = $this->find_manifest($zip, $entries);
			$files = [];
			$changelog = '';
			foreach ($entries as $zip_path => $stat)
			{
				$zip_path = (string) $zip_path;
				if (strpos($zip_path, $root) !== 0)
				{
					continue;
				}

				$relative = substr($zip_path, strlen($root));
				if (preg_match('~\A(?:\.git|vendor)/|(?:\A|/)(?:\.DS_Store|Thumbs\.db)\z~i', $relative))
				{
					continue;
				}
				$files[$relative] = $this->hash_entry($zip, $zip_path, $stat['size']);
				if ($read_changelog && strcasecmp($relative, 'CHANGELOG.md') === 0 && $stat['size'] <= markdown_notes::MAX_FILE_BYTES)
				{
					$contents = @$zip->getFromIndex($stat['index'], markdown_notes::MAX_FILE_BYTES + 1);
					if ($contents !== false && strlen($contents) === $stat['size'])
					{
						$changelog = $contents;
					}
				}
			}

			return ['name' => $name, 'version' => $version, 'files' => $files, 'changelog' => $changelog];
		}
		finally
		{
			$zip->close();
		}
	}

	private function entries(ZipArchive $zip)
	{
		if ($zip->numFiles > self::MAX_ENTRIES)
		{
			throw new RuntimeException('CL_ERR_ZIP_LIMIT');
		}

		$entries = [];
		$bytes = 0;
		for ($index = 0; $index < $zip->numFiles; $index++)
		{
			$stat = $zip->statIndex($index);
			if ($stat === false)
			{
				throw new RuntimeException('CL_ERR_ZIP_FORMAT');
			}

			$path = $stat['name'];
			if (strlen($path) > 500 || preg_match('//u', $path) !== 1
				|| preg_match('~\A/|//|(?:\A|/)\.\.?(?:/|\z)|[\\\\:\x00-\x1F\x7F]~', $path))
			{
				throw new RuntimeException('CL_ERR_ZIP_FORMAT');
			}

			if (substr($path, -1) === '/' || strpos($path, '__MACOSX/') === 0)
			{
				continue;
			}

			if (isset($entries[$path]) || !empty($stat['encryption_method'])
				|| ($zip->getExternalAttributesIndex($index, $system, $attributes)
					&& $system === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000))
			{
				throw new RuntimeException('CL_ERR_ZIP_FORMAT');
			}

			$bytes += $stat['size'];
			if ($bytes > self::MAX_UNCOMPRESSED)
			{
				throw new RuntimeException('CL_ERR_ZIP_LIMIT');
			}
			$entries[$path] = $stat;
		}

		return $entries;
	}

	private function find_manifest(ZipArchive $zip, array $entries)
	{
		$candidates = [];
		foreach ($entries as $path => $stat)
		{
			if (basename((string) $path) !== 'composer.json' || $stat['size'] > self::MAX_MANIFEST
				|| preg_match('~(?:\A|/)\.git/~', (string) $path))
			{
				continue;
			}

			$raw = @$zip->getFromIndex($stat['index'], self::MAX_MANIFEST + 1);
			$manifest = $raw === false ? null : json_decode($raw, true, 32);
			if (!is_array($manifest) || ($manifest['type'] ?? '') !== 'phpbb-extension'
				|| !is_string($manifest['name'] ?? null)
				|| !preg_match('~\A[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*\z~i', $manifest['name'])
				|| !self::valid_version($manifest['version'] ?? null))
			{
				continue;
			}

			$root = substr($path, 0, -strlen('composer.json'));
			$candidates[substr_count($root, '/')][] = [$root, strtolower($manifest['name']), $manifest['version']];
		}

		if ($candidates)
		{
			ksort($candidates, SORT_NUMERIC);
			$nearest = reset($candidates);
			if (count($nearest) === 1)
			{
				return reset($nearest);
			}
		}

		throw new RuntimeException('CL_ERR_MANIFEST');
	}

	private function hash_entry(ZipArchive $zip, $path, $size)
	{
		$stream = @$zip->getStream($path);
		if ($stream === false)
		{
			throw new RuntimeException('CL_ERR_ZIP_FORMAT');
		}

		try
		{
			$hash = hash_init('sha256');
			$bytes = @hash_update_stream($hash, $stream, $size + 1);
			if ($bytes !== $size)
			{
				throw new RuntimeException('CL_ERR_ZIP_FORMAT');
			}
			return hash_final($hash);
		}
		finally
		{
			fclose($stream);
		}
	}
}
