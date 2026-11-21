<?php

namespace mundophpbb\changelog\service;

/**
 * Accent- and case-insensitive search keys, stored next to product titles so
 * the public catalogue can be searched and paginated in SQL on every DBMS.
 */
class text_search
{
	public static function normalize($text)
	{
		$text = (string) $text;
		$decomposed = class_exists(\Normalizer::class) ? \Normalizer::normalize($text, \Normalizer::FORM_D) : false;
		$stripped = preg_replace('/\p{Mn}+/u', '', $decomposed === false ? $text : $decomposed);
		return mb_strtolower($stripped === null ? $text : $stripped, 'UTF-8');
	}
}
