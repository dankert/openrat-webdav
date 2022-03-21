<?php

namespace dav;

class Config {

	// Default-Configuration.
	public static $config  = [
		'dav.enable'               => false,
		'dav.create'               => true,
		'dav.readonly'             => false,
		'dav.expose_openrat'       => true,
		'dav.compliant_to_redmond' => true,
		'dav.redirect_collections_to_trailing_slash' => true,
		'dav.realm'                =>'OpenRat CMS WebDAV Login',
		'dav.anonymous'            => false,
		'cms.host'                 => 'localhost',
		'cms.port'                 => 80,
		'cms.username'             => null,
		'cms.password'             => null,
		'cms.database'             => 'db1',
		'cms.path'                 => '/',
		'cms.max_file_size'        => 1000,
		'log.level'                => 'info',
		'log.file'                 => null,
		'dav.path'                 => '',
		'dav.host'                 => '',
	];


	/**
	 * Configuration-Loader.
	 */
	public static function load() {

		Config::$config['dav.path'] =  $_SERVER['PHP_SELF' ];
		Config::$config['dav.host'] =  $_SERVER['HTTP_HOST'];

		$configFileLocations = [
			'dav.ini',
			'dav.custom.ini',
			'/etc/openrat-webdav.ini',
			'dav-'.$_SERVER['HTTP_HOST'].'.ini',
			getenv('DAV_CONFIG_FILE'),
		];
		foreach( $configFileLocations as $configFile )
			if   ( is_file($configFile))
				Config::$config = array_merge(Config::$config,parse_ini_file( $configFile) );

		function getValue($getenv)
		{
			if   ( in_array($getenv,['true','on','yes']))
				return true;
			if   ( in_array($getenv,['false','off','no']))
				return false;
			return $getenv;
		}

		// Config values are overwritable by Environment variables.
		array_walk(Config::$config, function(&$value, $key) {
			$envkey = strtoupper(str_replace('.','_',$key));
			if   ( $envValue = getValue(getenv($envkey)) )
				$value = $envValue;
		} );

	}
}

