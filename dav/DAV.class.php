<?php

define('LB',"\n");

abstract class DAV
{

	// Zahlreiche Instanzvariablen, die im Konstruktor
	// beim Zerlegen der Anfrag gef�llt werden.
	public $database;
	public $depth;
	public $projectid;
	public $request;
	public $filename;
	public $uri;
	public $headers;
	public $data;

	public $destination = null;
	public $sitePath;
	public $create;
	public $readonly;
	public $maxFileSize;
	public $webdav_conf;
	public $overwrite = false;

	private $httpMethod;

    /**
     * CMS-Client
     * @var CMS
     */
    protected $client;

    const MEMKEY = 1;
    private $shm;
    private $store;


    /**
	 * Im Kontruktor wird der Request analysiert und ggf. eine Authentifzierung
	 * durchgefuehrt. Anschließend wird eine interne Methode mit dem Namen davXXX() aufgerufen.
	 */
	function __construct()
	{
        // Dateitoken-Schlüssel ermitteln
        $key = ftok(__FILE__, 'a');
        // 0,5 MB
        $this->shm = shm_attach($key, 0.5 * 1000 * 1000, 0666);

        global $config;

        $this->httpMethod = strtoupper($_SERVER['REQUEST_METHOD']);

		Logger::trace( 'WEBDAV request' );
		
		if	( $config['dav.compliant_to_redmond'] )
			header('MS-Author-Via: DAV'           ); // Extrawurst fuer MS-Clients.
			
		if	( $config['dav.expose_openrat'] )
			header('X-Dav-powered-by: OpenRat CMS'); // Bandbreite verschwenden :)

 		Logger::trace( 'WEBDAV: URI='.$_SERVER['REQUEST_URI']);
		
		if	( !$config['dav.enable'])
		{
 			Logger::warn( 'WEBDAV is disabled by configuration' );
		
			$this->httpStatus('403 Forbidden');
			exit;
		}
		
		$this->create      = $config['dav.create'];
		$this->readonly    = $config['dav.readonly'];
		$this->maxFileSize = $config['cms.max_file_size'];
		
		$this->headers = getallheaders();
		/* DAV compliant servers MUST support the "0", "1" and
		 * "infinity" behaviors. By default, the PROPFIND method without a Depth
		 * header MUST act as if a "Depth: infinity" header was included. */
		if	( !isset($this->headers['Depth']) )
			$this->depth = 1;
		elseif	( strtolower($this->headers['Depth'])=='infinity')
			$this->depth = 1;
		else
			$this->depth = intval($this->headers['Depth']);

		if	( isset($this->headers['Destination']) )
			$this->destination = $this->headers['Destination'];

		$this->overwrite = @$this->headers['Overwrite'] == 'T';


        if	( @$config['dav.anonymous'])
            $username = @$config['cms.user'];
        else
            $username = @$_SERVER['PHP_AUTH_USER'];

        $this->username = $username;

        if   ( shm_has_var($this->shm, self::MEMKEY) )

            $this->store = shm_get_var($this->shm, self::MEMKEY);
        else
            $this->store = array();

        Logger::trace('Store at startup: '.LB.print_r($this->store,true) );

        if   ( is_array(@$this->store[$username]) )
            ;
        else
            $this->store[$username] = array();

        $this->client = new CMS();
        $this->client->client->cookies = $this->store[$username];

		if	( $this->store[$username] )
		{
			// Es gibt Cookies. Also:
            // Benutzer ist bereits im CMS eingeloggt.
		}
		else
		{
			// Login
			if	( $this->httpMethod != 'OPTIONS' ) // Bei OPTIONS kein Login anfordern
			{
				if	( isset($_SERVER['PHP_AUTH_USER']) )
				{
					
					$username = $_SERVER['PHP_AUTH_USER'];
					$pass     = $_SERVER['PHP_AUTH_PW'  ];
					
					try {
						$this->client->login($username, $pass, $config['cms.database']);

                        $this->store[$username] = $this->client->client->cookies;
                        shm_put_var($this->shm,self::MEMKEY, $this->store);
					}
					catch( Exception $e )
					{
						$this->httpStatus('401 Unauthorized');
						header('WWW-Authenticate: Basic realm="'.$config['dav.realm'].'"');
						error_log( print_r($e->getMessage(),true) );
						echo  'Failed login for user '.$username;
						exit;
					}
				}
				elseif	( $config['dav.anonymous'])
				{
					$username = $config['cms.user'];
					$pass     = $config['cms.password'];
					
					$loginOk = $this->client->login($username, $pass, $config['cms.database']);
					if	( !$loginOk ) {
						$this->httpStatus('500 Internal Server Error');
						echo 'Could not authenticate user '.$username;
						exit;
					}

					$this->store[$username] = $this->client->client->cookies;
                    shm_put_var($this->shm,self::MEMKEY, $this->store);
                }
				else
				{
					// Client ist nicht angemeldet, daher wird nun die
					// Authentisierung angefordert.
					header('WWW-Authenticate: Basic realm="'.$config['dav.realm'].'"');
					$this->httpStatus('401 Unauthorized');
					echo  'Authentification required for '.$config['dav.realm'];
					exit;
					
				}
			}
			else
			{
				return; // Bei OPTIONS müssen wir keine URL auswerten und können direkt zur Methode springen.
			}
		}
		
		
		$this->sitePath = $this->siteURL().$_SERVER['PHP_SELF'];

		// Path-Info. If not set, use '/'
        $pathInfo = @$_SERVER['PATH_INFO'] ? $_SERVER['PATH_INFO'] : '/';

        $this->request = new URIParser($this->client, $pathInfo);

        Logger::trace( $this->request->__toString() );

		/*
		 * Directories MUST end with a '/'. If not, redirect.
		 * 
		 * RFC 2518, 5.2 Collection Resources, Page 11:
		 * "For example, if a client invokes a
		 * method on http://foo.bar/blah (no trailing slash), the resource
		 * http://foo.bar/blah/ (trailing slash) may respond as if the operation
		 * were invoked on it, and should return a content-location header with
		 * http://foo.bar/blah/ in it.  In general clients SHOULD use the "/"
		 * form of collection names."
		 */
		if	( in_array($this->request->type,array('folder','root')) &&
			  substr($this->sitePath,-1 ) != '/' )
		    if   ( $config['dav.redirect_collections_to_trailing_slash'] )
            {
                // redirect the collection to append the trailing slash.
                // this is recommended by the spec (see above).
                Logger::debug( 'Redirecting lame client to slashyfied folder URL' );

                header('HTTP/1.1 302 Moved Temporarily');
                header('Location: '.$this->sitePath.'/');
                exit;
            }
            else
            {
                // no redirect - so we append the trailing slash.
                // this is allowed by the spec (see above).
                $this->sitePath .= '/';
            }


		// Falls vorhanden, den "Destination"-Header parsen.
		if	( isset($_SERVER['HTTP_DESTINATION']) )
		{
			$destUri = parse_url( $_SERVER['HTTP_DESTINATION'] );
			
			$uri = substr($destUri['path'],strlen($_SERVER['SCRIPT_NAME'])+$sos);
				
			// URL parsen.
			$this->destination = new URIParser( $this->client,$uri );
		}

		// Den Request-BODY aus der Standardeingabe lesen.
		$this->data = implode('',file('php://input'));
	}


	

	
    /**
     * Setzt einen HTTP-Status.<br>
     * <br>
     * Es wird ein HTTP-Status gesetzt, zus�tzlich wird der Status in den Header "X-DAV-Status" geschrieben.<br>
     * Ist der Status nicht 200 oder 207 (hier folgt ein BODY), wird das Skript beendet.
     */
    public static function httpStatus( $status = true )
    {
        if	( $status === true )
            $status = '200 OK';

        Logger::debug('WEBDAV: HTTP-Status: '.$status);

        header('HTTP/1.1 '.$status);
        header('X-DAV-Status: '.$status,true);
    }


    /**
     * Setzt einen HTTP-Status.<br>
     * <br>
     * Es wird ein HTTP-Status gesetzt, zus�tzlich wird der Status in den Header "X-DAV-Status" geschrieben.<br>
     * Ist der Status nicht 200 oder 207 (hier folgt ein BODY), wird das Skript beendet.
     */
    public static function httpForbidden()
    {
        $status = 403;
        header('HTTP/1.1 '.$status);
        header('X-WebDAV-Status: '.$status,true);
        Logger::debug('WEBDAV: HTTP-Status: '.$status);
    }


    /**
     * Setzt einen HTTP-Status.<br>
     * <br>
     * Es wird ein HTTP-Status gesetzt, zus�tzlich wird der Status in den Header "X-DAV-Status" geschrieben.<br>
     * Ist der Status nicht 200 oder 207 (hier folgt ein BODY), wird das Skript beendet.
     */
    public function httpMethodNotAllowed()
    {
        $status = 405;
        header('HTTP/1.1 '.$status);
        header('X-WebDAV-Status: '.$status,true);

        // RFC 2616 (HTTP/1.1), Section 10.4.6 "405 Method Not Allowed" says:
        //   "[...] The response MUST include an
        //    Allow header containing a list of valid methods for the requested
        //    resource."
        //
        // RFC 2616 (HTTP/1.1), Section 14.7 "Allow" says:
        //   "[...] An Allow header field MUST be
        //     present in a 405 (Method Not Allowed) response."
        header('Allow: '.implode(', ',$this->allowed_methods()) );

        self::httpStatus('405 Method Not Allowed');
    }




    protected function allowed_methods()
    {

        if	 ($this->readonly)
            return array('OPTIONS','HEAD','GET','PROPFIND');  // Readonly-Modus
        else
            // PROPPATCH unterstuetzen wir garnicht, aber lt. Spec sollten wir das.
            return array('OPTIONS','HEAD','GET','PROPFIND','DELETE','PUT','COPY','MOVE','MKCOL','PROPPATCH');
    }



    private function siteURL()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'];
        return $protocol.$domainName;
    }

    private function slashify( $path )
    {
        return $path.( substr( $path,-1 ) != '/' )?'/':'';
    }

    public function __destruct()
    {
        $this->store[$this->username] = $this->client->client->cookies;
        shm_put_var($this->shm,self::MEMKEY,$this->store);
    }


    public abstract function execute();
}
