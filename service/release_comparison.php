<?php

namespace mundophpbb\changelog\service;

class release_comparison
{
	public static function compare(array $base, array $target)
	{
		$sections = [];
		foreach (release_notes::SECTIONS as $field => $label)
		{
			$before = release_notes::lines($base[$field]);
			$after = release_notes::lines($target[$field]);
			if ($before === $after)
			{
				continue;
			}

			$removed = self::remaining_items($before, $after);
			$added = self::remaining_items($after, $before);
			$sections[] = [
				'label' => $label,
				'removed' => $removed,
				'added' => $added,
				'reordered' => !$removed && !$added,
			];
		}

		$links = [];
		foreach (release_notes::LINKS as $field => $label)
		{
			if ($base[$field] !== $target[$field])
			{
				$links[] = ['label' => $label, 'before' => $base[$field], 'after' => $target[$field]];
			}
		}

		return ['sections' => $sections, 'links' => $links];
	}

	private static function remaining_items(array $items, array $other)
	{
		$counts = array_count_values($other);
		$remaining = [];
		foreach ($items as $item)
		{
			if (!empty($counts[$item]))
			{
				$counts[$item]--;
			}
			else
			{
				$remaining[] = $item;
			}
		}

		return $remaining;
	}
}
