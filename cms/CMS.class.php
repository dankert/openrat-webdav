<?php

namespace cms;

use dav\Config;
use dav\exception\CMSForbiddenError;
use dav\exception\CMSServerError;
use dav\Logger;

define('CMS_READ'  ,'GET' );
define('CMS_WRITE' ,'POST');


/**
 * Highlevel-API for accessing the CMS.
 */
class CMS
{
	var $login = false;


	public $client;

	public function __construct()
    {
        $this->client = new Client();

		$this->client->host   = Config::$config['cms.host'];
		$this->client->port   = Config::$config['cms.port'];
		$this->client->path   = Config::$config['cms.path'];
		$this->client->ssl    = false;
    }



	
	function projectlist()
	{
		$result = $this->call(CMS_READ,'projectlist','edit' );

		return $result;
	}

	
	function project($projectid)
	{
		$result = $this->call(CMS_READ,'project','prop',array('id'=>$projectid) );
	
		return $result;
	}

	function projectAdd($name)
	{
		$result = $this->call(CMS_WRITE,'projectlist','add',array('name'=>$name) );

		return $result;
	}

	function folder($id)
	{
		$content = $this->call(CMS_READ,'folder','edit',array('id'=>$id) );
		$prop    = $this->call(CMS_READ,'folder','info',array('id'=>$id) );

		return( array( 'content'=>$content, 'properties'=>$prop ) );
	}

	function folderAdd($parentid,$name)
	{
		$result = $this->call(CMS_WRITE,'folder','createfolder',array('id'=>$parentid,'name'=>$name) );

		return $result;
	}

	function page($id)
	{
		$result = $this->call(CMS_READ,'page','edit',array('id'=>$id) );
	
		return $result;
	}
	
	function link($id)
	{
		$result = $this->call(CMS_READ,'link','edit',array('id'=>$id) );
	
		return $result;
	}
	
	function url($id)
	{
		$result = $this->call(CMS_READ,'url','edit',array('id'=>$id) );

		return $result;
	}

	function file($id)
	{
		$result = $this->call(CMS_READ,'file','edit',array('id'=>$id) );
	
		return $result;
	}

	function projectDelete($id)
	{
		$result = $this->call(CMS_WRITE,'project','remove',array('id'=>$id,'delete'=>'true') );
		return $result;


	}

    function folderDelete($id)
	{
		$result = $this->call(CMS_WRITE,'folder','remove',array('id'=>$id,'delete'=>'true','withChildren'=>'true') );

		return $result;
	}


    function pageDelete($id)
    {
        $result = $this->call(CMS_WRITE,'page','remove',array('id'=>$id,'delete'=>'true') );

        return $result;
    }
    function fileDelete($id)
	{
		$result = $this->call(CMS_WRITE,'file','remove',array('id'=>$id,'delete'=>'true') );

		return $result;
	}

	function imageDelete($id)
	{
		$result = $this->call(CMS_WRITE,'image','remove',array('id'=>$id,'delete'=>'true') );

		return $result;
	}

	function textDelete($id)
	{
		$result = $this->call(CMS_WRITE,'text','remove',array('id'=>$id,'delete'=>'true') );

		return $result;
	}

    function filevalue($id)
	{
		$result = $this->call(CMS_READ,'file','show',array('id'=>$id) );

		return $result;
	}


    public function fileWrite($id,$value)
    {
        $result = $this->call(CMS_WRITE,'file','edit',array('id'=>$id,'value'=>$value) );

        return $result;
    }

    public function fileAdd($parentid,$filename,$value)
    {
        $result = $this->call(CMS_WRITE,'folder','createfile',array('id'=>$parentid,'filename'=>$filename,'value'=>$value) );

        return $result;
    }


	/**
	 * @throws CMSServerError
	 * @throws CMSForbiddenError
	 */
	protected function call($method, $action, $subaction, $parameter=array() )
    {
        Logger::trace( "CMS-Request : Executing  $method $action/$subaction"."\n".$this->__toString() );

		try {
			$result =  $this->client->call( $method,$action,$subaction,$parameter );
		}
		catch( \RuntimeException $e ) {
			switch( $e->getCode() ) {
				case 403:
					throw new CMSForbiddenError( 'Forbidden',$e );
				default:
					throw new CMSServerError( $e->getMessage(),$e );
			}
		}

        Logger::trace( "CMS-Response: API-Result $method $action/$subaction:"."\n".$this->__toString()."\n".print_r($result,true));

        return $result;
    }

    public function __toString()
    {
        return print_r( get_object_vars($this),true);
    }

	public function setCredentials($username, $pass)
	{
		$this->client->setCredentials( $username,$pass );
	}

	public function setDatabaseId($databaseId)
	{
		$this->client->setDatabaseId( $databaseId );
	}

}
