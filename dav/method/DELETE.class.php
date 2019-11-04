<?php

class DAV_DELETE extends DAV
{

		
	/**
	 * Objekt l�schen.
	 */		
	public function execute()
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

}
