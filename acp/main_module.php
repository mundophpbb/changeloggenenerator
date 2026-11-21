<?php

namespace mundophpbb\changelog\acp;

class main_module
{
	public $u_action;
	public $tpl_name;
	public $page_title;

	public function main($id, $mode)
	{
		global $phpbb_container;

		$this->tpl_name = 'acp_mphpbb_changelog';
		$this->page_title = 'ACP_MPHPBB_CL_MANAGE';
		$phpbb_container->get('mundophpbb.changelog.admin')->handle($this->u_action);
	}
}
