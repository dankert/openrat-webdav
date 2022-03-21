<?php


use dav\exception\CMSServerError;
use dav\exception\CMSForbiddenError;

/**
 * Low-level-API for accessing the Openrat CMS API.
 */
class Client
{
    public $success;

    protected $responseHeader;
    protected $parameterString;
    protected $requestHeader;

	private $user = null;
	private $password = null;
	private $databaseId = null;

	public $host = 'localhost';
	public $port = 80;
	public $path = '/';
	public $ssl = false;

	public function setCredentials( $username,$password ) {
	$this->user = $username;
	$this->password = $password;
   }


   public function setDatabaseId( $databaseId ) {
	$this->databaseId = $databaseId;
   }
	public function call( $method,$action,$subaction,$parameter=[] )
	{
		if   ( $this->databaseId )
			$parameter['dbid'] = $this->databaseId;

		$status = '';

		$errno  = 0;
		$errstr = '';

		$host   = $this->host;
		$port   = $this->port;
		$path   = $this->path;
		
		// Vorbedingungen checken:
		// Slash an Anfang und Ende?
		if	( substr($path,-1 ) != '/' )
			$path = $path.'/';
		if	( substr($path,0,1 ) != '/' )
			$path = '/'.$path;
		$path .= '/';
		
		// Methode: Fallback GET
		if	( !$method )
			$method='GET';

		// Die Funktion fsockopen() erwartet eine Protokollangabe (bei TCP optional, bei SSL notwendig).
		if	( $port == '443' || $this->ssl )
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

			$this->parameterString = '';

			foreach( $parameter as $name=>$value )
			{
				if	( $this->parameterString )
					$this->parameterString .= '&';
					
				$this->parameterString .= urlencode($name).'='.urlencode($value);
			}
			
			if	( $method == 'GET')
					$http_get .= '?'.$this->parameterString;


			$this->requestHeader = [];
			
			$this->requestHeader[] = $method.' '.$http_get.' HTTP/1.0';
			$this->requestHeader[] = 'Host: '.$host;
			$this->requestHeader[] = 'Accept: application/php-serialized';

			if ( $this->user)
				$this->requestHeader[] = 'Authorization: Basic '.base64_encode($this->user.':'.$this->password);

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

			$result = unserialize($body);
			if 	( $result === false )
			{
				throw new RuntimeException('The server response cannot be unserialized into a PHP array');
			}
			else
			{
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
