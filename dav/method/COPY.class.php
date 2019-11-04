<?php

class DAV_COPY extends DAV
{

	/**
	 * Kopieren eines Objektes.<br>
	 * Momentan ist nur das Kopieren einer Datei implementiert.<br>
	 * Das Kopieren von Ordnern, Verkn�pfungen und Seiten ist nicht moeglich.
	 */		
	public function execute()
	{
		if	( $this->readonly || !$this->create )
		{
			error_log('WEBDAV: COPY request, but readonly or no creating');
			$this->httpMethodNotAllowed();
		}
		elseif( $this->obj == null )
		{
			// Was nicht da ist, laesst sich auch nicht verschieben.
			error_log('WEBDAV: COPY request, but Source not found');
			$this->httpMethodNotAllowed();
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
				$this->httpMethodNotAllowed();
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
				$this->httpMethodNotAllowed();
			}
			elseif ( is_object($destinationObject) && $destinationObject->isLink)
			{
				Logger::debug('WEBDAV: COPY request denied, Link copy not implemented');
				$this->httpMethodNotAllowed();
			}
			elseif ( is_object($destinationObject) && $destinationObject->isPage)
			{
				Logger::debug('WEBDAV: COPY request denied, Page copy not implemented');
				$this->httpMethodNotAllowed();
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


}
