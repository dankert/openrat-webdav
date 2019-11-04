<?php

define('LB',"\n");
class WebDAV
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
    private $client;

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
	 * HTTP-Methode OPTIONS.<br>
	 * <br>
	 * Es werden die verfuegbaren Methoden ermittelt und ausgegeben.
	 */
	public function davOPTIONS()
	{
		header('DAV: 1'); // Wir haben DAV-Level 1.
		header('Allow: '.implode(', ',$this->allowed_methods()) );
		
		Logger::trace('OPTIONS: '.'Allow: '.implode(', ',$this->allowed_methods()));

		$this->httpStatus( '200 OK' );
	}
	
	



	/**
	 * WebDav-HEAD-Methode.
	 */	
	public function davHEAD()
	{
		if	( ! $this->request->objectid )
		{
			$this->httpStatus( '404 Not Found' );
		}
		elseif	( $this->request->type == 'folder' )
		{
			$this->httpStatus( '200 OK' );
		}
		elseif( $this->obj->isPage )
		{
			$this->httpStatus( '200 OK' );
		}
		elseif( $this->obj->isLink )
		{
			$this->httpStatus( '200 OK' );
		}
		elseif( $this->obj->isFile )
		{
			$this->httpStatus( '200 OK' );
		}
	}
	
	
	
	/**
	 * WebDav-GET-Methode.
	 * Die gew�nschte Datei wird geladen und im HTTP-Body mitgeliefert.
	 */	
	public function davGET()
	{
		switch( $this->request->type )
		{
			case 'root':
			case 'folder':
				$this->getDirectoryIndex();
				break;
				
			case 'page':
				$this->httpStatus( '200 OK' );
				header('Content-Type: text/html');
			
				echo '<html><head><title>OpenRat WEBDAV Access</title></head>';
				echo '<body>';
				echo '<h1>'.$this->request->type.'</h1>';
				echo '<pre>';
				echo 'No Content available';
				echo '</pre>';
				echo '</body>';
				echo '</html>';
				break;
				
			case 'link':
				$this->httpStatus( '200 OK' );
			
				header('Content-Type: text/plain');
				
				$link = $this->client->link( $this->request->objectid );
				echo 'url: '      .$link['url']           ."\n";
				echo 'target-id: '.$link['linkedObjectId']."\n";
				
				break;
				
			case 'file':
			case 'image':
			case 'text':
				$this->httpStatus( '200 OK' );
				
				$file      = $this->client->file     ( $this->request->objectid );
				$filevalue = $this->client->filevalue( $this->request->objectid );
				
				header('Content-Type: '.$file['mimetype']);
				header('X-File-Id: '   .$this->request->objectid );
		
				// Angabe Content-Disposition
				// - Bild soll "inline" gezeigt werden
				// - Dateiname wird benutzt, wenn der Browser das Bild speichern moechte
				header('Content-Disposition: inline; filename='.$file['filename'].'.'.$file['extension'] );
				header('Content-Transfer-Encoding: binary' );
				header('Content-Description: '.$file['filename'].'.'.$file['extension'] );
		
				// Groesse des Bildes in Bytes
				// Der Browser hat so die Moeglichkeit, einen Fortschrittsbalken zu zeigen
				header('Content-Length: '.$file['size'] );
				
				echo $filevalue;
				
				break;

            default:
                $this->httpStatus( '200 OK' );
                header('Content-Type: text/html');

                echo '<html><head><title>OpenRat WEBDAV Access</title></head>';
                echo '<body>';
                echo '<h1>'.$this->request->type.'</h1>';
                echo '<pre>';
                echo 'Unknown node type: '.$this->request->type;
                echo '</pre>';
                echo '</body>';
                echo '</html>';
                break;


        }
	}
	
	
	
	/**
	 * Erzeugt ein Unix-�hnliche Ausgabe des Verzeichnisses als HTML.
	 */
	private function getDirectoryIndex()
	{
		$this->httpStatus( '200 OK' );
		
		// Verzeichnis ausgeben
		header('Content-Type: text/html');
		$nl = "\n";
		
		
		

		$titel = 'Index of '.htmlspecialchars($this->sitePath);
		$format = "%15s  %-19s  %-s\n";
		
		echo '<html><head><title>'.$titel.'</title></head>';
		echo '<body>';
		echo '<h1>'.$titel.'</h1>'.$nl;
		echo '<pre>';
		
		printf($format, "Size", "Last modified", "Filename");
		
		
		switch( $this->request->type )
		{
			case 'root':  // Projektliste
		
				$result = $this->client->projectlist();
				$projects = $result['projects'];
				foreach( $projects as $projectid=>$p )
				{
					echo '<a href="'.$p['name'].'">'.$p['name'].'</a>'.$nl;
				}
				break;						
		
			case 'folder':  // Verzeichnisinhalt
		
				$folder = $this->client->folder( $this->request->objectid );
					
				foreach( $folder['content']['object'] as $object )
				{
					
					printf($format,
						number_format(1),
						strftime("%Y-%m-%d %H:%M:%S",$object['date'] ),
						'<a href="./'.$object['filename'].'">'.$object['filename'].'</a>');
					echo $nl;
						
				}
		}		
		
		echo '</pre>';
		echo '</body>';
		echo '</html>';
	}
	
	

	/**
	 * Die Methode LOCK sollte garnicht aufgerufen werden, da wir nur
	 * Dav-Level 1 implementieren und dies dem Client auch mitteilen.<br>
	 * <br>
	 * Ausgabe von HTTP-Status 412 (Precondition failed)
	 */	
	function davLOCK()
	{
		$this->httpStatus('412 Precondition failed');
		$this->davOPTIONS();
	}


		
	/**
	 * Die Methode UNLOCK sollte garnicht aufgerufen werden, da wir nur
	 * Dav-Level 1 implementieren und dies dem Client auch mitteilen.<br>
	 * <br>
	 * Ausgabe von HTTP-Status 412 (Precondition failed)
	 */	
	public function davUNLOCK()
	{
		$this->httpStatus('412 Precondition failed');
		$this->davOPTIONS();
	}



	/**
	 * Die Methode POST ist bei WebDav nicht sinnvoll.<br>
	 * <br>
	 * Ausgabe von HTTP-Status 405 (Method Not Allowed)
	 */	
	public function davPOST()
	{
		// Die Methode POST ist bei Webdav nicht sinnvoll.
		$this->httpStatus('405 Method Not Allowed' );
	}
	
	
	
	/**
	 * Verzeichnis anlegen.
	 */		
	public function davMKCOL()
	{
		
		if	( !empty($this->data) )
		{
			$this->httpStatus('415 Unsupported Media Type' ); // Kein Body erlaubt
		}
		elseif	( $this->readonly )
		{
			$this->httpStatus('403 Forbidden' ); // Kein Schreibzugriff erlaubt
		}
		elseif  ( !$this->folder->hasRight( ACL_CREATE_FOLDER ) )
		{
			$this->httpStatus('403 Forbidden' ); // Benutzer darf das nicht
		}
		elseif	( $this->obj == null )
		{
			// Die URI ist noch nicht vorhanden
			$f = new Folder();
			$f->filename  = basename($this->sitePath);
			$f->parentid  = $this->folder->objectid;
			$f->projectid = $this->project->projectid;
			$f->add();
			$this->httpStatus('201 Created');
		}
		else
		{
			// MKCOL ist nicht moeglich, wenn die URI schon existiert.
			Logger::warn('MKCOL-Request to an existing resource');
			$this->httpStatus('405 Method Not Allowed' );
		}
	}


		
	/**
	 * Objekt l�schen.
	 */		
	public function davDELETE()
	{
		if	( $this->readonly )
		{
			$this->httpStatus('403 Forbidden' ); // Kein Schreibzugriff erlaubt
		}
		else
		{
			if	( $this->obj == null )
			{
				// Nicht existente URIs kann man auch nicht loeschen.
				$this->httpStatus('404 Not Found' );
			}
			elseif  ( ! $this->obj->hasRight( ACL_DELETE ) )
			{
				$this->httpStatus('403 Forbidden' ); // Benutzer darf die Resource nicht loeschen
			}
			elseif	( $this->obj->isFolder )
			{
				$f = new Folder( $this->obj->objectid );
				$f->deleteAll();
				$this->httpStatus( true ); // OK
				Logger::debug('Deleted folder with id '.$this->obj->objectid );
			}
			elseif	( $this->obj->isFile )
			{
				$f = new File( $this->obj->objectid );
				$f->delete();
				$this->httpStatus( true ); // OK
			}
			elseif	( $this->obj->isPage )
			{
				$p = new Page( $this->obj->objectid );
				$p->delete();
				$this->httpStatus( true ); // OK
			}
			elseif	( $this->obj->isLink )
			{
				$l = new Link( $this->obj->objectid );
				$l->delete();
				$this->httpStatus( true ); // OK
			}

		}
	}


		
	/**
	 * Kopieren eines Objektes.<br>
	 * Momentan ist nur das Kopieren einer Datei implementiert.<br>
	 * Das Kopieren von Ordnern, Verkn�pfungen und Seiten ist nicht moeglich.
	 */		
	public function davCOPY()
	{
		if	( $this->readonly || !$this->create )
		{
			error_log('WEBDAV: COPY request, but readonly or no creating');
			$this->httpStatus('405 Not Allowed' );
		}
		elseif( $this->obj == null )
		{
			// Was nicht da ist, laesst sich auch nicht verschieben.
			error_log('WEBDAV: COPY request, but Source not found');
			$this->httpStatus('405 Not Allowed' );
		}
		elseif ( $this->destination == null )
		{
			error_log('WEBDAV: COPY request, but no "Destination:"-Header');
			// $this->httpStatus('405 Not Allowed' );
			$this->httpStatus('412 Precondition failed');
		}
		else
		{
			// URL parsen.
			$dest = $this->destination;
			$destinationProject = $dest['project'];
			$destinationFolder  = $dest['folder' ];
			$destinationObject  = $dest['object' ];
			
			if	( $dest['type'] != 'object' )
			{
				Logger::debug('WEBDAV: COPY request, but "Destination:"-Header mismatch');
				$this->httpStatus('405 Not Allowed');
			}
			elseif	( $this->project->projectid != $destinationProject->projectid )
			{
				// Kopieren in anderes Projekt nicht moeglich.
				Logger::debug('WEBDAV: COPY request denied, project does not match');
				$this->httpStatus('403 Forbidden');
			}
			elseif	( $destinationObject != null )
			{
				Logger::debug('WEBDAV: COPY request denied, Destination exists. Overwriting is not supported');
				$this->httpStatus('403 Forbidden');
			}
			elseif ( is_object($destinationFolder) && ! $destinationFolder->hasRight( ACL_CREATE_FILE ) )
			{
				$this->httpStatus('403 Forbidden' ); // Benutzer darf das nicht
			}
			elseif ( is_object($destinationObject) && $destinationObject->isFolder)
			{
				Logger::debug('WEBDAV: COPY request denied, Folder-Copy not implemented');
				$this->httpStatus('405 Not Allowed');
			}
			elseif ( is_object($destinationObject) && $destinationObject->isLink)
			{
				Logger::debug('WEBDAV: COPY request denied, Link copy not implemented');
				$this->httpStatus('405 Not Allowed');
			}
			elseif ( is_object($destinationObject) && $destinationObject->isPage)
			{
				Logger::debug('WEBDAV: COPY request denied, Page copy not implemented');
				$this->httpStatus('405 Not Allowed');
			}
			else
			{
				$f = new File();
				$f->filename = basename($_SERVER['HTTP_DESTINATION']);
				$f->name     = '';
				$f->parentid = $destinationFolder->objectid;
				$f->projectid = $this->project->projectid;
				$f->add();
				$f->copyValueFromFile( $this->obj->objectid );
				
				Logger::debug('WEBDAV: COPY request accepted' );
				// Objekt wird in anderen Ordner kopiert.
				$this->httpStatus('201 Created' );
			}	
		}

	}


		
	/**
	 * Verschieben eines Objektes.<br>
	 * <br>
	 * Folgende Operationen sind m�glich:<br>
	 * - Unbenennen eines Objektes (alle Typen)<br> 
	 * - Verschieben eines Objektes (alle Typen) in einen anderen Ordner.<br>
	 */		
	public function davMOVE()
	{
		if	( $this->readonly )
		{
			$this->httpStatus('403 Forbidden - Readonly Mode' ); // Schreibgeschuetzt
		}
		elseif	( !$this->create )
		{
			$this->httpStatus('403 Forbidden - No creation' ); // Schreibgeschuetzt
		}
		elseif( $this->obj == null )
		{
			// Was nicht da ist, laesst sich auch nicht verschieben.
			$this->httpStatus('404 Not Found' );
		}
		elseif( is_object($this->obj) && ! $this->obj->hasRight( ACL_WRITE ) )
		{
			// Was nicht da ist, laesst sich auch nicht verschieben.
			Logger::error('Source '.$this->obj->objectid.' is not writable: Forbidden');
			$this->httpStatus('403 Forbidden' );
		}
		elseif ( $this->destination == null )
		{
			Logger::error('WEBDAV: MOVE request, but no "Destination:"-Header');
			// $this->httpStatus('405 Not Allowed' );
			$this->httpStatus('412 Precondition failed');
		}
		else
		{
			$dest = $this->destination;
			$destinationProject = $dest['project'];
			$destinationFolder  = $dest['folder' ];
			$destinationObject  = $dest['object' ];

			if	( $dest['type'] != 'object' )
			{
				Logger::debug('WEBDAV: MOVE request, but "Destination:"-Header mismatch');
				$this->httpStatus('405 Not Allowed');
				return;
			}

			if	( is_object($destinationFolder) && ! $destinationFolder->hasRight( ACL_CREATE_FILE ) )
			{
				Logger::error('Source '.$this->obj->objectid.' is not writable: Forbidden');
				$this->httpStatus('403 Forbidden' );
			}
			
			if	( $destinationObject != null )
			{
				Logger::debug('WEBDAV: MOVE request denied, destination exists');
				$this->httpStatus('412 Precondition Failed');
				return;
			}
			
			if	( $this->project->projectid != $destinationProject->projectid )
			{
				// Verschieben in anderes Projekt nicht moeglich.
				Logger::debug('WEBDAV: MOVE request denied, project does not match');
				$this->httpStatus('405 Not Allowed');
				return;
			}
			
			if	( $this->folder->objectid == $destinationFolder->objectid )
			{
				Logger::debug('WEBDAV: MOVE request accepted, object renamed');
				// Resource bleibt in gleichem Ordner.
				$this->obj->filename = basename($_SERVER['HTTP_DESTINATION']);
				$this->obj->objectSave(false);
				$this->httpStatus('201 Created' );
				return;
			}
			
			if	( $destinationFolder->isFolder )
			{
				Logger::debug('WEBDAV: MOVE request accepted, Destination: '.$destinationFolder->filename );
				// Objekt wird in anderen Ordner verschoben.
				$this->obj->setParentId( $destinationFolder->objectid );
				$this->httpStatus('201 Created' );
				return;
			}
			
			Logger::warn('WEBDAV: MOVE request failed' );
			$this->httpStatus('500 Internal Server Error' );
		}
	}


		
	/**
	 * Anlegen oder �berschreiben Dateien �ber PUT.<br>
	 * Dateien k�nnen neu angelegt und �berschrieben werden.<br>
	 * <br>
	 * Seiten k�nnen nicht �berschrieben werden. Wird versucht,
	 * eine Seite mit PUT zu �berschreiben, wird der Status "405 Not Allowed" gemeldet.<br>
	 */		
	public function davPUT()
	{
		// TODO: 409 (Conflict) wenn übergeordneter Ordner nicht da.

		if	( $this->readonly )
		{
			$this->httpStatus('405 Not Allowed' );
		}		
		elseif	( strlen($this->data) > $this->maxFileSize*1000 )
		{
			// Maximale Dateigroesse ueberschritten.
			// Der Status 207 "Zuwenig Speicherplatz" passt nicht ganz, aber fast :)
			$this->httpStatus('507 Insufficient Storage' );
		}
		elseif	( ! $this->request->objectid )
		{
			// Neue Datei anlegen
			if	( !$this->create )
			{
				Logger::warn('WEBDAV: Creation of files not allowed by configuration' );
				$this->httpStatus('405 Not Allowed' );
			}
			
			$this->client->fileAdd( $this->data );
			$this->httpStatus('201 Created');
			return;
		}
		elseif	( $this->request->objectid )
		{
			// Bestehende Datei ueberschreiben.
			$id = $this->request->objectid;
            $this->client->fileAdd( $id,$this->data );

			$this->httpStatus('204 No Content');
			return;
		}
		elseif	( $this->obj->isFolder )
		{
			Logger::error('PUT on folder is not supported, use PROPFIND. Lame client?' );
			$this->httpStatus('405 Not Allowed' );
		}
		else
		{
			// Fuer andere Objekttypen (Links, Seiten) ist kein PUT moeglich.
			Logger::warn('PUT only available for files. Pages and links are ignored' );
			$this->httpStatus('405 Not Allowed' );
		}
	}
	
	

	/**
	 * WebDav-Methode PROPFIND.
	 * 
	 * Diese Methode wird
	 * - beim Ermitteln von Verzeichnisinhalten und
	 * - beim Ermitteln von Metainformationen zu einer Datei
	 * verwendet.
	 * 
	 * Das Ergebnis wird in einer XML-Zeichenkette geliefert.
	 */	
	public function davPROPFIND()
	{
		switch( $this->request->type )
		{
			case 'root':  // Projektliste
				
				$inhalte = array();
				
				$objektinhalt = array();
				$z = 30*365.25*24*60*60;
				$objektinhalt['createdate'    ] = $z;
				$objektinhalt['lastchangedate'] = $z;
				$objektinhalt['size'          ] = 1;
				$objektinhalt['name'          ] = $this->sitePath;
				$objektinhalt['displayname'   ] = '';
				$objektinhalt['type']           = 'folder';

				$inhalte[] = $objektinhalt;

				try {
                    $result = $this->client->projectlist();
                } catch( Exception $e) {
				    Logger::error($e->__toString().$this->client->__toString());
				    throw $e;
                }
				$projects = $result['projects'];
				foreach( $projects as $projectid=>$p )
				{
					$objektinhalt = array();
					$objektinhalt['createdate'    ] = TIME_20000101;
					$objektinhalt['lastchangedate'] = TIME_20000101;
					$objektinhalt['size'          ] = 1;
					$objektinhalt['name'          ] = $this->sitePath.$p['name'].'/';
					$objektinhalt['displayname'   ] = $p['name'];
					$objektinhalt['type']           = 'folder';
					$inhalte[] = $objektinhalt;
				}
					
				$this->multiStatus( $inhalte );
				break;

			case 'folder':  // Verzeichnisinhalt

				$folder = $this->client->folder( $this->request->objectid );

				$inhalte = array();

				$objektinhalt = array();
				$objektinhalt['createdate'    ] = $folder['properties']['create_date'];
				$objektinhalt['lastchangedate'] = $folder['properties']['lastchange_date'];
				$objektinhalt['name'          ] = $this->sitePath;
				$objektinhalt['displayname'   ] = $folder['properties']['filename'];
				$objektinhalt['type'          ] = 'folder';
				$objektinhalt['size'          ] = 0;

				$inhalte[] = $objektinhalt;
					
				if	( $this->depth > 0 )
				{
					
					foreach( $folder['content']['object'] as $object )
					{
						$objektinhalt = array();
						$objektinhalt['createdate'    ] = $object['date'];
						$objektinhalt['lastchangedate'] = $object['date'];
						$objektinhalt['displayname'   ] = $object['filename'];

						switch( $object['type'] )
						{
							case 'folder':
								$objektinhalt['name'] = $this->sitePath.$object['filename'].'/';
								$objektinhalt['type'] = 'folder';
								$objektinhalt['size'] = 0;
								$inhalte[] = $objektinhalt;
								break;
							case 'file':
							case 'image':
							case 'text':
								$objektinhalt['name'] = $this->sitePath.$object['filename'];
								$objektinhalt['type'] = 'file';
								$objektinhalt['size'] = $object['size'];
								$objektinhalt['mime'] = 'application/x-non-readable';
								$inhalte[] = $objektinhalt;
								break;
							case 'link':
								$objektinhalt['name'] = $this->sitePath.$object['filename'];
								$objektinhalt['type'] = 'file';
								$objektinhalt['size'] = 0;
								$objektinhalt['mime'] = 'application/x-non-readable';
								$inhalte[] = $objektinhalt;
								break;
							case 'url':
							case 'alias':
								$objektinhalt['name'] = $this->sitePath.$object['filename'];
								$objektinhalt['type'] = 'file';
								$objektinhalt['size'] = 0;
								$objektinhalt['mime'] = 'application/x-non-readable';
								$inhalte[] = $objektinhalt;
								break;
							case 'page':
								$objektinhalt['name'] = $this->sitePath.$object['filename'];
								$objektinhalt['type'] = 'file';
								$objektinhalt['size'] = 0;
								$inhalte[] = $objektinhalt;
								break;
							default:
						}
					}
				}
				$this->multiStatus( $inhalte );
				break;
				
			case 'page':
				$page = $this->client->page( $this->request->objectid );
				$prop = $page['properties'];
				$objektinhalt = array();
				$objektinhalt['name']           = $this->sitePath;
				$objektinhalt['displayname']    = $prop['filename'];
				$objektinhalt['createdate'    ] = $prop['date'];
				$objektinhalt['lastchangedate'] = $prop['date'];
				
				$objektinhalt['size'          ] = 0;
				$objektinhalt['type'          ] = 'file';

                $this->multiStatus( array($objektinhalt) );

                break;
				
			case 'file':
			case 'text':
			case 'image':
				$file = $this->client->file( $this->request->objectid );
				$objektinhalt = array();
				$objektinhalt['name']           = $this->sitePath;
				$objektinhalt['displayname']    = $file['filename'];
				$objektinhalt['createdate'    ] = $file['date'];
				$objektinhalt['lastchangedate'] = $file['date'];
				
				$objektinhalt['size'          ] = $file['size'];
				$objektinhalt['type'          ] = 'file';

                $this->multiStatus( array($objektinhalt) );

            break;
				
			case 'link':
				
				$link = $this->client->link( $this->request->objectid );
				
				$objektinhalt = array();
				$objektinhalt['name']           = $this->sitePath;
				$objektinhalt['displayname']    = $link['filename'];
				$objektinhalt['createdate'    ] = $link['date'];
				$objektinhalt['lastchangedate'] = $link['date'];

				$objektinhalt['size'          ] = 0;
				$objektinhalt['type'          ] = 'file';
				
				
				$this->multiStatus( array($objektinhalt) );
				
				break;
				
			case 'url':

				$link = $this->client->link( $this->request->objectid );

				$objektinhalt = array();
				$objektinhalt['name']           = $this->sitePath;
				$objektinhalt['displayname']    = $link['filename'];
				$objektinhalt['createdate'    ] = $link['date'];
				$objektinhalt['lastchangedate'] = $link['date'];

				$objektinhalt['size'          ] = 0;
				$objektinhalt['type'          ] = 'file';


				$this->multiStatus( array($objektinhalt) );

				break;

			default:
				Logger::warn('Internal Error, unknown request type: '. $this->request->type);
				$this->httpStatus('500 Internal Server Error');
		}
	}
	
	
	/**
	 * Webdav-Methode PROPPATCH ist nicht implementiert.
	 */
	public function davPROPPATCH()
	{
		// TODO: Multistatus erzeugen.
		// Evtl. ist '409 Conflict' besser?
		$this->httpStatus('405 Not Allowed');
	}
	
	
	/**
	 * Erzeugt einen Multi-Status.
	 * @access private
	 */
	private function multiStatus( $files )
	{
		$this->httpStatus('207 Multi-Status');
		header('Content-Type: text/xml; charset=utf-8');
		
		$response = '';
		$response .= '<?xml version="1.0" encoding="utf-8" ?>';
		$response .= '<d:multistatus xmlns:d="DAV:">';

		foreach( $files as $file )
			$response .= $this->getResponse( $file['name'],$file );
		
		$response .= '</d:multistatus>';

		$response = utf8_encode($response);

		header('Content-Length: '.strlen($response));
 		Logger::trace('Sending Multistatus:'."\n".$response);
		echo $response;
	}
	
	
	/**
	 * Erzeugt ein "response"-Element, welches in ein "multistatus"-element verwendet werden kann.
	 */
	private function getResponse( $file,$options )
	{
		// TODO: Nur angeforderte Elemente erzeugen.
		$response = '';
		$response .= '<d:response>';
		$response .= '<d:href>'.$file.'</d:href>';
		$response .= '<d:propstat>';
		$response .= '<d:prop>';
		//		$response .= '<d:source></d:source>';
		$response .= '<d:creationdate>'.date('r',$options['createdate']).'</d:creationdate>';
		$response .= '<d:displayname>'.$options['displayname'].'</d:displayname>';
		$response .= '<d:getcontentlength>'.$options['size'].'</d:getcontentlength>';
		$response .= '<d:getlastmodified xmlns:b="urn:uuid:c2f41010-65b3-11d1-a29f-00aa00c14882/" b:dt="dateTime.rfc1123">'.date('r',$options['lastchangedate']).'</d:getlastmodified>';
		
		if	( $options['type'] == 'folder')
			$response .= '<d:resourcetype><d:collection/></d:resourcetype>';
		else
			$response .= '<d:resourcetype />';
			
		$response .= '<d:categories />';
		$response .= '<d:fields></d:fields>';
		
		
		
//		$response .= '<d:getcontenttype>text/html</d:getcontenttype>';
//		$response .= '<d:getcontentlength />';
//		$response .= '<d:getcontentlanguage />';
//		$response .= '<d:executable />';
//		$response .= '<d:resourcetype>';
//		$response .= '<d:collection />';
//		$response .= '</d:resourcetype>';
//		$response .= '<d:getetag />';

		$response .= '</d:prop>';
		$response .= '<d:status>HTTP/1.1 200 OK</d:status>';
		$response .= '</d:propstat>';
		$response .= '</d:response>';

		return $response;		
	}
	
	
	
    /**
     * Setzt einen HTTP-Status.<br>
     * <br>
     * Es wird ein HTTP-Status gesetzt, zus�tzlich wird der Status in den Header "X-WebDAV-Status" geschrieben.<br>
     * Ist der Status nicht 200 oder 207 (hier folgt ein BODY), wird das Skript beendet.
     */
    protected function httpStatus( $status = true )
    {
        if	( $status === true )
            $status = '200 OK';

        // 		Logger::debug('WEBDAV: HTTP-Status: '.$status);

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
        if	( substr($status,0,3) == '405' )
            header('Allow: '.implode(', ',$this->allowed_methods()) );
    }




    private function allowed_methods()
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
}