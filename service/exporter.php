<?php

namespace mundophpbb\changelog\service;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds the Markdown and BBCode downloads offered in the ACP.
 */
class exporter
{
	/**
	 * Single release as CHANGELOG.md or as a BBCode text file.
	 */
	public function release(array $product, array $release, array $labels, $markdown)
	{
		if ($markdown)
		{
			return self::download(
				new Response(markdown_notes::render($product['title'], $release, $labels)),
				'CHANGELOG.md',
				'text/markdown; charset=UTF-8'
			);
		}

		return self::download(
			new Response(release_notes::bbcode($product['title'], $release, $labels) . "\n"),
			$product['slug'] . '-' . $release['version'] . '-bbcode.txt',
			'text/plain; charset=UTF-8'
		);
	}

	/**
	 * Full published history, streamed so long histories do not need to fit in memory.
	 *
	 * @param iterable $entries Published releases, newest first.
	 */
	public function history(array $product, iterable $entries, array $labels, $bbcode)
	{
		$response = new StreamedResponse(static function () use ($entries, $product, $labels, $bbcode) {
			$output = fopen('php://output', 'wb');
			fwrite($output, ($bbcode ? release_notes::bbcode_heading($product['title']) : markdown_notes::title($product['title'])) . "\n");
			foreach ($entries as $release)
			{
				$date = gmdate('Y-m-d', (int) $release['published_at']);
				fwrite($output, "\n" . ($bbcode
					? release_notes::bbcode_history_release($release, $labels, $date)
					: markdown_notes::render_history_release($release, $labels, $date)));
			}
			fclose($output);
		});

		return $bbcode
			? self::download($response, $product['slug'] . '-changelog-bbcode.txt', 'text/plain; charset=UTF-8')
			: self::download($response, 'CHANGELOG.md', 'text/markdown; charset=UTF-8');
	}

	/**
	 * Parses a YYYY-MM-DD date (UTC). Returns null for anything else, including impossible dates.
	 */
	public static function parse_date($value)
	{
		if (!is_string($value) || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value))
		{
			return null;
		}

		$date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
		return $date && $date->format('Y-m-d') === $value && (int) $date->format('Y') > 0 ? $date : null;
	}

	private static function download(Response $response, $filename, $content_type)
	{
		$response->headers->add([
			'Content-Type' => $content_type,
			'Content-Disposition' => 'attachment; filename="' . $filename . '"',
			'Cache-Control' => 'private, no-store',
			'X-Content-Type-Options' => 'nosniff',
		]);

		return $response;
	}
}
