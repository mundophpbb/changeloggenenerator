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
	'LOG_CL_RELEASE_DELETED' => '<strong>Changelog: versão excluída</strong><br>» %1$s %2$s',
	'LOG_CL_PRODUCT_DELETED' => '<strong>Changelog: produto excluído</strong><br>» %s',
	'ACL_A_CHANGELOG' => 'Pode gerenciar changelogs',
	'ACP_MPHPBB_CL_MANAGE' => 'Gerenciador de changelog',
]);
