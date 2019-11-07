<?php

class DAV_HEAD extends DAV
{

	/**
	 * WebDav-HEAD-Methode.
	 */	
	public function execute()
	{
		if	( ! $this->request->exists() )
		{
			$this->httpStatus( '404 Not Found' );
		}
		else
		{
			$this->httpStatus( '200 OK' );
		}
	}
}
