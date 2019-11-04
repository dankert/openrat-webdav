<?php

class DAV_POST extends DAV
{

	/**
	 * Die Methode POST ist bei WebDav nicht sinnvoll.<br>
	 * <br>
	 * Ausgabe von HTTP-Status 405 (Method Not Allowed)
	 */	
	public function execute()
	{
		// Die Methode POST ist bei Webdav nicht sinnvoll.
		$this->httpMethodNotAllowed();
	}
}
