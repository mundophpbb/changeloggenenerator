<?php

namespace mundophpbb\changelog\service;

use RuntimeException;

/**
 * Reads uploaded CHANGELOG.md files and turns their versions into drafts.
 *
 * The bulk preview hands the uploaded Markdown back to the browser as a signed
 * payload, so the confirmation step does not need a second upload.
 */
class bulk_import
{
	public const PREVIEW_LIFETIME = 1200;

	private $repository;

	public function __construct(repository $repository)
	{
		$this->repository = $repository;
	}

	/**
	 * Reads an uploaded Markdown file.
	 *
	 * @throws RuntimeException With the language key $error when the upload is missing, too large or unreadable.
	 */
	public static function read_upload($upload, $max_bytes, $error)
	{
		$upload = is_array($upload) ? $upload : [];
		$path = $upload['tmp_name'] ?? '';
		if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
			|| (int) ($upload['size'] ?? 0) > $max_bytes
			|| !is_string($path) || !is_uploaded_file($path))
		{
			throw new RuntimeException($error);
		}

		$markdown = @file_get_contents($path, false, null, 0, $max_bytes + 1);
		if ($markdown === false || strlen($markdown) > $max_bytes)
		{
			throw new RuntimeException($error);
		}

		return $markdown;
	}

	public static function signature($secret, $session_id, $product_id, $expires, $payload)
	{
		$context = 'changelog-import:' . $session_id . ':' . (int) $product_id . ':' . (int) $expires . ':' . $payload;
		return hash_hmac('sha256', $context, (string) $secret);
	}

	/**
	 * Verifies and decodes a signed preview payload.
	 *
	 * @throws RuntimeException CL_BULK_INVALID_PREVIEW when the payload expired, was altered or is too large.
	 */
	public static function decode_payload($payload, $expires, $signature, $secret, $session_id, $product_id, $now = null)
	{
		$now = $now ?? time();
		if (!is_string($payload) || !is_string($signature)
			|| strlen($payload) > 4 * (int) ceil(markdown_notes::MAX_BULK_BYTES / 3)
			|| (int) $expires < $now
			|| !hash_equals(self::signature($secret, $session_id, $product_id, $expires, $payload), $signature))
		{
			throw new RuntimeException('CL_BULK_INVALID_PREVIEW');
		}

		$markdown = base64_decode($payload, true);
		if ($markdown === false || strlen($markdown) > markdown_notes::MAX_BULK_BYTES)
		{
			throw new RuntimeException('CL_BULK_INVALID_PREVIEW');
		}

		return $markdown;
	}

	/**
	 * Rows for the preview table: one per version found in the file.
	 */
	public function preview($product_id, array $entries)
	{
		$existing = $this->repository->existing_versions($product_id, array_column($entries, 'version'));
		$rows = [];
		foreach ($entries as $key => $entry)
		{
			$notes = array_merge([], ...array_map([release_notes::class, 'lines'], array_values($entry['notes'])));
			$rows[$key] = [
				'key' => $key,
				'version' => $entry['version'],
				'existing' => isset($existing[$key]),
				'count' => count($notes),
			];
		}

		return ['rows' => $rows, 'existing' => count($existing)];
	}

	/**
	 * Saves each new version as a draft. Versions that already exist are skipped.
	 *
	 * @return array{saved: int, skipped: int, failed: int}
	 */
	public function import_drafts($product_id, array $entries)
	{
		$existing = $this->repository->existing_versions($product_id, array_column($entries, 'version'));
		$result = ['saved' => 0, 'skipped' => 0, 'failed' => 0];
		foreach ($entries as $key => $entry)
		{
			if (isset($existing[$key]))
			{
				$result['skipped']++;
				continue;
			}
			$data = ['version' => $entry['version'], 'previous_version' => '', 'published_at' => 0]
				+ $entry['notes'] + array_fill_keys(array_keys(release_notes::SECTIONS + release_notes::LINKS), '');
			if ($this->repository->save_release($data, $product_id, false, []))
			{
				$result['saved']++;
			}
			elseif ($this->repository->release_for_version($product_id, $entry['version']))
			{
				$result['skipped']++;
			}
			else
			{
				$result['failed']++;
			}
		}

		return $result;
	}
}
