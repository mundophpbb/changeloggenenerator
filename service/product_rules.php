<?php

namespace mundophpbb\changelog\service;

/**
 * Validation rules for products, shared by the create and edit forms.
 */
class product_rules
{
	public const MAX_TITLE_LENGTH = 120;
	public const SLUG_PATTERN = '/\A[a-z0-9][a-z0-9-]{0,79}\z/';

	public static function valid_title($title)
	{
		return is_string($title) && $title !== ''
			&& mb_strlen($title, 'UTF-8') <= self::MAX_TITLE_LENGTH
			&& !preg_match('/[\x00-\x1F\x7F]/', $title);
	}

	public static function valid_slug($slug)
	{
		return is_string($slug) && (bool) preg_match(self::SLUG_PATTERN, $slug);
	}

	/**
	 * @return string|null Language key of the first problem, or null when valid.
	 */
	public static function error($title, $download, $support, $slug = null)
	{
		if (!self::valid_title($title))
		{
			return 'CL_ERR_TITLE';
		}
		if ($slug !== null && !self::valid_slug($slug))
		{
			return 'CL_ERR_SLUG';
		}
		if ($download !== '' && !release_notes::valid_url($download))
		{
			return 'CL_ERR_PRODUCT_DOWNLOAD';
		}
		if ($support !== '' && !release_notes::valid_url($support))
		{
			return 'CL_ERR_PRODUCT_SUPPORT';
		}

		return null;
	}
}
