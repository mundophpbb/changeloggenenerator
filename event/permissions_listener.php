<?php

namespace mundophpbb\changelog\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Shows the a_changelog permission in ACP > Permissions, under "Misc".
 */
class permissions_listener implements EventSubscriberInterface
{
	public static function getSubscribedEvents()
	{
		return ['core.permissions' => 'add_permission'];
	}

	public function add_permission($event)
	{
		$permissions = $event['permissions'];
		$permissions['a_changelog'] = ['lang' => 'ACL_A_CHANGELOG', 'cat' => 'misc'];
		$event['permissions'] = $permissions;
	}
}
