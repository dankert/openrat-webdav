<?php

namespace dav\method;

use dav\DAV;
use dav\Logger;
use dav\MultistatusObject;
use dav\URIParser;
use Exception;

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
				
				$objektinhalt = new MultistatusObject();
				$z = 30*365.25*24*60*60;
				$objektinhalt->creationDate     = $z;
				$objektinhalt->lastmodifiedDate = $z;
				$objektinhalt->name             = $this->sitePath;
				$objektinhalt->type             = MultistatusObject::TYPE_FOLDER;

				$inhalte = [ $objektinhalt ];

                $result = $this->client->projectlist();
				$projects = $result['projects'];
				foreach( $projects as $projectid=>$p )
				{
					$objektinhalt = new MultistatusObject();
					$objektinhalt->name  = $this->sitePath.$p['name'].'/';
					$objektinhalt->label = $p['name'];
					$objektinhalt->type  = MultistatusObject::TYPE_FOLDER;

					$inhalte[] = $objektinhalt;
				}
					
				$this->multiStatus( $inhalte );
				break;

			case 'folder':  // Verzeichnisinhalt

				$folder = $this->client->folder( $this->request->objectid );

				$objektinhalt = new MultistatusObject();
				$objektinhalt->creationDate     = $folder['properties']['create_date'];
				$objektinhalt->lastmodifiedDate = $folder['properties']['lastchange_date'];
				$objektinhalt->name             = $this->request->uri;
				$objektinhalt->label            = $folder['properties']['filename'];
				$objektinhalt->type             = MultistatusObject::TYPE_FOLDER;

				$inhalte = [ $objektinhalt ];
					
				if	( $this->depth > 0 )
				{
					foreach( $folder['content']['object'] as $object )
					{
						$objektinhalt = new MultistatusObject();
						$objektinhalt->creationDate     = $object['date'];
						$objektinhalt->lastmodifiedDate = $object['date'];
						$objektinhalt->label            = $object['filename'];

						switch( $object['type'] )
						{
							case 'folder':
								$objektinhalt->name = $this->request->uri.$object['filename'].'/';
								$objektinhalt->type = MultistatusObject::TYPE_FOLDER;
								break;
							case 'file':
							case 'image':
							case 'text':
								$objektinhalt->name = $this->request->uri.$object['filename'];
								$objektinhalt->type = MultistatusObject::TYPE_FILE;
								$objektinhalt->size = $object['size'];
								$objektinhalt->mimeType = 'application/x-non-readable';
								break;
							case 'link':
							case 'url':
							case 'alias':
								$objektinhalt->name = $this->request->uri.$object['filename'];
								$objektinhalt->type = MultistatusObject::TYPE_FILE;
								$objektinhalt->mimeType = 'application/x-non-readable';
								break;
							case 'page':
								$objektinhalt->name = $this->request->uri.$object['filename'];
								$objektinhalt->type = MultistatusObject::TYPE_FILE;
								break;
							default:
						}
						$inhalte[] = $objektinhalt;
					}
				}
				$this->multiStatus( $inhalte );
				break;
				
			case 'page':
				$page = $this->client->page( $this->request->objectid );
				$prop = $page['properties'];
				$objektinhalt = new MultistatusObject();
				$objektinhalt->name             = $this->request->uri;
				$objektinhalt->label            = $prop['filename'];
				$objektinhalt->creationDate     = $prop['date'];
				$objektinhalt->lastmodifiedDate = $prop['date'];
				$objektinhalt->type             = MultistatusObject::TYPE_FILE;

                $this->multiStatus( [ $objektinhalt ] );

                break;
				
			case 'file':
			case 'text':
			case 'image':
				$file = $this->client->file( $this->request->objectid );
				$objektinhalt = new MultistatusObject();
				$objektinhalt->name             = $this->request->uri;
				$objektinhalt->label            = $file['filename'];
				$objektinhalt->creationDate     = $file['date'];
				$objektinhalt->lastmodifiedDate = $file['date'];
				$objektinhalt->size             = $file['size'];
				$objektinhalt->type = MultistatusObject::TYPE_FILE;

                $this->multiStatus( [ $objektinhalt ] );

            break;
				
			case 'link':
				
				$link = $this->client->link( $this->request->objectid );
				
				$objektinhalt = new MultistatusObject();
				$objektinhalt->name  = $this->request->uri;
				$objektinhalt->label = $link['filename'];
				$objektinhalt->creationDate     = $link['date'];
				$objektinhalt->lastmodifiedDate = $link['date'];
				$objektinhalt->type = MultistatusObject::TYPE_FILE;

				
				$this->multiStatus( [ $objektinhalt ] );
				
				break;
				
			case 'url':

				$link = $this->client->url( $this->request->objectid );

				$objektinhalt = new MultistatusObject();
				$objektinhalt->name  = $this->request->uri;
				$objektinhalt->label = $link['filename'];
				$objektinhalt->creationDate     = $link['date'];
				$objektinhalt->lastmodifiedDate = $link['date'];
				$objektinhalt->type = MultistatusObject::TYPE_FILE;

				$this->multiStatus( [ $objektinhalt ] );

				break;

			default:
				Logger::warn('Internal Error, unknown request type: '. $this->request->type);
				$this->httpStatus('500 Internal Server Error');
		}
	}




    /**
     * Erzeugt einen Multi-Status.
     * @access private
     */
    protected function multiStatus( $files )
    {
        $this->httpStatus('207 Multi-Status');
        header('Content-Type: text/xml; charset=utf-8');

        $response = '';
        $response .= '<?xml version="1.0" encoding="utf-8" ?>';
        $response .= '<d:multistatus xmlns:d="DAV:">';

		/** @var MultistatusObject $options */
		foreach($files as $options ) {

			$response .= '<d:response>';
			$response .= '<d:href>'.$options->name.'</d:href>';
			$response .= '<d:propstat>';
			$response .= '<d:prop>';
			//		$response .= '<d:source></d:source>';
			$response .= '<d:creationdate>'.date('r',$options->creationDate).'</d:creationdate>';
			$response .= '<d:displayname>'.$options->label.'</d:displayname>';
			$response .= '<d:getcontentlength>'.$options->size.'</d:getcontentlength>';
			$response .= '<d:getlastmodified xmlns:b="urn:uuid:c2f41010-65b3-11d1-a29f-00aa00c14882/" b:dt="dateTime.rfc1123">'.date('r',$options->lastmodifiedDate).'</d:getlastmodified>';

			if	( $options->type == MultistatusObject::TYPE_FOLDER)
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

		}


        $response .= '</d:multistatus>';

        $response = utf8_encode($response);

        header('Content-Length: '.strlen($response));
        Logger::trace('Sending Multistatus:'."\n".$response);
        echo $response;
    }

}
