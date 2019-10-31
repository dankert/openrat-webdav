<?php 

define('CMS_READ'  ,'GET' );
define('CMS_WRITE' ,'POST');

class CMS
{
	var $login = false;
	var $token;

	private $client;

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
	
	function folder($id)
	{
		$content = $this->call(CMS_READ,'folder','edit',array('id'=>$id) );
		$prop    = $this->call(CMS_READ,'folder','info',array('id'=>$id) );

		return( array( 'content'=>$content, 'properties'=>$prop ) );
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

    function filevalue($id)
	{
		$result = $this->call(CMS_READ,'file','show',array('id'=>$id),true );

		return $result;
	}


    public function fileWrite($id,$value)
    {
        $result = $this->call(CMS_WRITE,'file','save',array('id'=>$id,'value'=>$value) );

        return $result;
    }

    public function fileAdd($value)
    {
        $result = $this->call(CMS_WRITE,'file','save',array('value'=>$value) );

        return $result;
    }


    protected function call( $method,$action,$subaction,$parameter=array(),$direct=false )
    {
        Logger::trace( "Executing     $method $action/$subaction"."\n".$this->__toString() );

        $result =  $this->client->call( $method,$action,$subaction,$parameter,false );

        Logger::trace( "API-Result of $method $action/$subaction:"."\n".$this->__toString()."\n".print_r($result,true));

        return $result;
    }

    public function __toString()
    {
        return print_r( get_object_vars($this),true);
    }

}
