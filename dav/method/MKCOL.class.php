<?php

class DAV_MKCOL extends DAV
{

	/**
	 * Verzeichnis anlegen.
	 */		
	public function execute()
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
			$this->httpMethodNotAllowed();
		}
	}

}
