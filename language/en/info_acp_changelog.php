<?php

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'ACP_MPHPBB_CL' => 'Changelog',
	'LOG_CL_RELEASE_DELETED' => '<strong>Changelog: release deleted</strong><br>» %1$s %2$s',
	'LOG_CL_PRODUCT_DELETED' => '<strong>Changelog: product deleted</strong><br>» %s',
	'ACL_A_CHANGELOG' => 'Can manage changelogs',
	'ACP_MPHPBB_CL_MANAGE' => 'Changelog Manager',
]);
