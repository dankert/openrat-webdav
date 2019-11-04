<?php

class DAV_PROPFIND extends DAV
{

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
	public function execute()
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
}
