<?php

class DAV_PROPPATCH extends DAV
{

	/**
	 * Webdav-Methode PROPPATCH ist nicht implementiert.
	 */
	public function execute()
	{
		// TODO: Multistatus erzeugen.
		// Evtl. ist '409 Conflict' besser?
		$this->httpMethodNotAllowed();
	}
	
}
