<?php

namespace mundophpbb\changelog\event;

use phpbb\config\config;
use phpbb\controller\helper;
use phpbb\language\language;
use phpbb\template\template;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class navigation_listener implements EventSubscriberInterface
{
	private $config;
	private $helper;
	private $language;
	private $template;

	public function __construct(config $config, helper $helper, language $language, template $template)
	{
		$this->config = $config;
		$this->helper = $helper;
		$this->language = $language;
		$this->template = $template;
	}

	public static function getSubscribedEvents()
	{
		return ['core.page_header' => 'add_public_link'];
	}

	public function add_public_link()
	{
		if (defined('ADMIN_START') || empty($this->config['mphpbb_cl_show_navigation']))
		{
			return;
		}

		$this->language->add_lang('common', 'mundophpbb/changelog');
		$this->template->assign_var('CL_NAV_URL', $this->helper->route('mundophpbb_changelog_index'));
	}
}
