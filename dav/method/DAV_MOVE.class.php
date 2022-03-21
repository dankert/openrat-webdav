<?php

namespace dav\method;

use dav\DAV;
use dav\Logger;

class DAV_MOVE extends DAV
{
	/**
	 * Verschieben eines Objektes.<br>
	 * <br>
	 * Folgende Operationen sind m�glich:<br>
	 * - Unbenennen eines Objektes (alle Typen)<br> 
	 * - Verschieben eines Objektes (alle Typen) in einen anderen Ordner.<br>
	 */		
	public function execute()
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
				$this->httpMethodNotAllowed();
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
				$this->httpMethodNotAllowed();
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
}
