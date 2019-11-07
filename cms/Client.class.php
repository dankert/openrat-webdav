<?php


use dav\exception\CMSServerError;
use dav\exception\CMSForbiddenError;

class Client
{
    public $useCookies = false;
    public $success;

    protected $action;
    protected $subaction;

    public $cookies = array();
    protected $sessionName;
    protected $sessionId;

    protected $token;

    protected $method; // GET oder POST

    protected $responseHeader;
    protected $parameterString;
    protected $requestHeader;


	public function call($method,$action,$subaction,$parameter=array())
	{
		global $config;
		$error  = '';
		$status = '';

		$errno  = 0;
		$errstr = '';

		$host   = $config['cms.host'];
		$port   = $config['cms.port'];
		$path   = $config['cms.path'];
		
		// Vorbedingungen checken:
		// Slash an Anfang und Ende?
		if	( substr($path,-1 ) != '/' )
			$path = $path.'/';
		if	( substr($path,0,1 ) != '/' )
			$path = '/'.$path;
		$path .= '/api/';
		
		// Methode: Fallback GET
		if	( !$method )
			$method='GET';

		// Die Funktion fsockopen() erwartet eine Protokollangabe (bei TCP optional, bei SSL notwendig).
		if	( $port == '443' || @$config['ssl'] )
			$prx_proto = 'ssl://'; // SSL
		else
			$prx_proto = 'tcp://'; // Default
			
		$fp = fsockopen ($prx_proto.$host,$port, $errno, $errstr, 30);

		if	( !$fp || !is_resource($fp) )
		{
			echo "Connection refused: '".$prx_proto.$host.':'.$port." - $errstr ($errno)";
		}
		else
		{
			$lb = "\r\n";
			$http_get = $path;

			$parameter += array('action'=>$action,'subaction'=>$subaction);
			if	( $method=='POST')
				$parameter += array('token'=>$this->token);
				
			$this->parameterString = '';

			foreach( $parameter as $name=>$value )
			{
				if	( $this->parameterString )
					$this->parameterString .= '&';
					
				$this->parameterString .= urlencode($name).'='.urlencode($value);
			}
			
			if	( $method == 'GET')
					$http_get .= '?'.$this->parameterString;

			$this->requestHeader = array();
			
			$this->requestHeader[] = $method.' '.$http_get.' HTTP/1.0';
			$this->requestHeader[] = 'Host: '.$host;
			$this->requestHeader[] = 'Accept: application/php-serialized';
			
			if	( $this->useCookies)
            {
                $cookies = array();;
                foreach( $this->cookies as $cookieName=>$cookieValue)
                    $cookies[] = $cookieName.'='.$cookieValue;
                $this->requestHeader[] = 'Cookie: '.implode('; ',$cookies);

            }

			//if	( ! empty($this->sessionName))
			//	$this->requestHeader[] = 'Cookie: '.$this->sessionName.'='.$this->sessionId;
				
			if	( $method == 'POST' )
			{
				$this->requestHeader[] = 'Content-Type: application/x-www-form-urlencoded';
				$this->requestHeader[] = 'Content-Length: '.strlen($this->parameterString);
			}
					
			$http_request = implode($lb,$this->requestHeader).$lb.$lb;
			
			if	( $method == 'POST' )
			{
				$http_request .= $this->parameterString;
			}
			if (!is_resource($fp)) {
				$error = 'Connection lost after connect: '.$prx_proto.$host.':'.$port;
				return false;
			}
			fputs($fp, $http_request); // Die HTTP-Anfrage zum Server senden.

			// Jetzt erfolgt das Auslesen der HTTP-Antwort.
			$isHeader = true;

			// RFC 1945 (Section 6.1) schreibt als Statuszeile folgendes Format vor
			// "HTTP/" 1*DIGIT "." 1*DIGIT SP 3DIGIT SP
			if (!is_resource($fp)) {
				echo 'Connection lost during transfer: '.$host.':'.$port;
			}
			elseif (!feof($fp)) {
				$line = fgets($fp,1028);
				$status = substr($line,9,3);

			}
			else
			{
				echo 'Unexpected EOF while reading HTTP-Response';
			}
			
			$body='';
			while (!feof($fp)) {
				$line = fgets($fp,1028);
				if	( $isHeader && trim($line)=='' ) // Leerzeile nach Header.
				{
					$isHeader = false;
				}
				elseif( $isHeader )
				{
					list($headerName,$headerValue) = explode(': ',$line) + array(1=>'');
					$this->responseHeader[$headerName] = trim($headerValue);
				}
				else
				{
					$body .= $line;
				}
			}
			fclose($fp); // Verbindung brav schlie�en.

            if   ( @$status == '200' )
                ; // OK
            elseif   ( @$status != '403' )
            {
                throw new CMSForbiddenError('CMS: Forbidden'."$line\n".$body);
            }
            elseif   ( @$status[0] == '5' )
            {
                throw new CMSServerError('Internal CMS Error'."$line\n".$body);
            }
            else
            {
                throw new RuntimeException('Server-Status: '.@$status."$line\n".$body);
            }

			foreach( $this->responseHeader as $headerName => $headerValue)
			{
				if	( $headerName == 'Set-Cookie' )
				{
					$parts = explode(';',$headerValue);
					$payload = $parts[0];
					list( $cookieName,$cookieValue) = explode('=',$payload);
					{
						$this->cookies[trim($cookieName)] = trim($cookieValue);
					}
				}
			}

			$result = unserialize($body);
			if 	( $result === false )
			{
				throw new RuntimeException('The server response cannot be unserialized into a PHP array');
			}
			else
			{
				$this->sessionName = $result['session']['name'];
				$this->sessionId   = $result['session']['id'];
				$this->token       = $result['session']['token'];

				$this->success     = @$result['success'] == 'true';
				$this->notices     = $result['notices'];

                return $result['output'];
            }

		}
	}

    public function __toString()
    {
        return print_r( get_object_vars($this),true);
    }
}
