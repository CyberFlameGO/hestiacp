<?php

namespace Hestia\WebApp\Installers\DokuWiki;

use Hestia\System\Util;
use \Hestia\WebApp\Installers\BaseSetup as BaseSetup;

class DokuWikiSetup extends BaseSetup {

	protected $appInfo = [ 
		'name' => 'DokuWiki',
		'group' => 'wiki',
		'enabled' => true,
		'version' => 'stable_2022-07-31a',
		'thumbnail' => 'dokuwiki-logo.svg'
	];
	
	protected $appname = 'dokuwiki';
	protected $extractsubdir = "/tmp-dokuwiki";

	protected $config = [
		'form' => [
			'wiki_name' => 'text',
			'superuser' => 'text',
			'real_name' => 'text',
			'email' => 'text',
			'password' => 'password',
			'initial_ACL_policy' => [ 
				'type' => 'select',
				'options' => [
					'0: Open Wiki (read, write, upload for everyone)', // 0
					'1: Public Wiki (read for everyone, write and upload for registered users)', // 1
					'2: Closed Wiki (read, write, upload for registered users only)' // 3
			    	],
			],
			'content_license' => [
				'type' => 'select',
				'options' => [
					'cc-zero: CC0 1.0 Universal',
					'publicdomain: Public Domain',
					'cc-by: CC Attribution 4.0 International',
					'cc-by-sa: CC Attribution-Share Alike 4.0 International',
					'gnufdl: GNU Free Documentation License 1.3',
					'cc-by-nc: CC Attribution-Noncommercial 4.0 International',
					'cc-by-nc-sa: CC Attribution-Noncommercial-Share Alike 4.0 International',
					'0: Do not show any license information',
				]	
			],
		 ],
		'resources' => [
			'archive'  => [ 'src' => 'https://github.com/splitbrain/dokuwiki/archive/refs/tags/release_stable_2022-07-31a.zip' ],
		],
		'server' => [
			'nginx' => [
				'template' => 'default'
			],
			'php' => [ 
				'supported' => [ '7.3','7.4','8.0','8.1' ],
			]
		], 
	];
	
	public function install(array $options = null, &$status=null)
	{
		parent::install($options);
		parent::setup($options);
			
		//check if ssl is enabled 
        $this->appcontext->run('v-list-web-domain', [$this->appcontext->user(), $this->domain, 'json'], $status);
		$sslEnabled = ($status->json[$this->domain]['SSL'] == 'no' ? 0 : 1);

		$webDomain = ($sslEnabled ? "https://" : "http://") . $this->domain . "/";
		
		$this->appcontext->runUser('v-copy-fs-directory',[
			$this->getDocRoot($this->extractsubdir . "/dokuwiki-release_stable_2022-07-31a/."),
			$this->getDocRoot()], $status);

		// enable htaccess
		$this->appcontext->runUser('v-move-fs-file', [$this->getDocRoot(".htaccess.dist"), $this->getDocRoot(".htaccess")], $status);

		$installUrl = $webDomain . "install.php";

		$cmd = "curl --request POST "
		  . ($sslEnabled ? "" : "--insecure " )
		  . "--url $installUrl "
		  . "--header 'Content-Type: application/x-www-form-urlencoded' "
		  . "--data l=en "
		  . "--data 'd[title]=" . rawurlencode($options['wiki_name']) . "' "
		  . "--data 'd[acl]=on' "
		  . "--data 'd[superuser]=" . rawurlencode($options['superuser']) . "' "
		  . "--data 'd[fullname]=" . rawurlencode($options['real_name']) . "' "
		  . "--data 'd[email]=" . rawurlencode($options['email']) . "' "
		  . "--data 'd[password]=" . rawurlencode($options['password']) . "' "
		  . "--data 'd[confirm]=" . rawurlencode($options['password']) . "' "
		  . "--data 'd[policy]=" . substr(rawurlencode($options['initial_ACL_policy']), 0, 1) . "' "
		  . "--data 'd[license]=" . explode(":", rawurlencode($options['content_license'])[0]) . "' "
		  . "--data submit=";

		exec($cmd, $output, $return_var);
		if($return_var > 0){
			throw new \Exception(implode( PHP_EOL, $output));
		}
		// remove temp folder
		$this->appcontext->runUser('v-delete-fs-file', [$this->getDocRoot("install.php")], $status);
		$this->cleanup();

		return ($status->code === 0);
	}
}