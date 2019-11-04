<?php

class DAV_HEAD extends DAV
{

	/**
	 * WebDav-HEAD-Methode.
	 */	
	public function execute()
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
}
