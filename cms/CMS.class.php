<?php 

define('CMS_READ'  ,'GET' );
define('CMS_WRITE' ,'POST');

class CMS
{
	var $login = false;
	var $token;

	public $client;

	public function __construct()
    {
        $this->client = new Client();
        $this->client->useCookies = true;

    }

    function login($user, $password,$dbid )
	{
		
		// Erster Request der Sitzung muss ein GET-Request sein.
		// Hier wird auch der Token gelesen.
		$result = $this->call(CMS_READ,'login','login' );
		
		$result = $this->call(CMS_WRITE,'login','login',array('login_name'=>$user,'login_password'=>$password,'dbid'=>$dbid) );
		
		if	( ! $this->client->success ) {
			throw new Exception( 'Login failed.',true );
		}

		$this->login = true;

		return $this->login;
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


    protected function call( $method,$action,$subaction,$parameter=array() )
    {
        Logger::trace( "Executing     $method $action/$subaction"."\n".$this->__toString() );

        $result =  $this->client->call( $method,$action,$subaction,$parameter );

        Logger::trace( "API-Result of $method $action/$subaction:"."\n".$this->__toString()."\n".print_r($result,true));

        return $result;
    }

    public function __toString()
    {
        return print_r( get_object_vars($this),true);
    }

}
