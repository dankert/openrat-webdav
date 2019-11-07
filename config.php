<?php

// Default-Configuration.
$config = array(
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
    'log.file'                 => null
);

// Configuration-Loader
foreach( array(
             'dav.ini',
             'dav.custom.ini',
             '/etc/openrat-webdav.ini',
             'dav-'.$_SERVER['HTTP_HOST'].'.ini',
             @$_ENV['DAV_CONFIG_FILE']
         ) as $iniFile )
    if   ( is_file($iniFile))
        $config = array_merge($config,parse_ini_file( $iniFile) );


// Config values are overwritable by Environment variables.
array_walk($config, function(&$value,$key) {
    $envkey = strtoupper(str_replace('.','_',$key));
    if   ( @$_ENV[$envkey] )
        $value = $_ENV[$envkey];
} );
