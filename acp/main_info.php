<?php

namespace mundophpbb\changelog\acp;

class main_info
{
	public function module()
	{
		return [
			'filename' => '\mundophpbb\changelog\acp\main_module',
			'title' => 'ACP_MPHPBB_CL',
			'modes' => [
				'manage' => [
					'title' => 'ACP_MPHPBB_CL_MANAGE',
					'auth' => 'ext_mundophpbb/changelog && acl_a_changelog',
					'cat' => ['ACP_MPHPBB_CL'],
				],
			],
		];
	}
}
