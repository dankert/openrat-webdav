<?php

namespace dav\method;

use dav\DAV;
use dav\Logger;
use dav\URIParser;

class DAV_PUT extends DAV
{

		
	/**
	 * Anlegen oder �berschreiben Dateien �ber PUT.<br>
	 * Dateien k�nnen neu angelegt und �berschrieben werden.<br>
	 * <br>
	 * Seiten k�nnen nicht �berschrieben werden. Wird versucht,
	 * eine Seite mit PUT zu �berschreiben, wird der Status "405 Not Allowed" gemeldet.<br>
	 */		
	public function execute()
	{
		// TODO: 409 (Conflict) wenn übergeordneter Ordner nicht da.

		if	( $this->readonly )
		{
			$this->httpMethodNotAllowed();
		}		
		elseif	( strlen($this->data) > $this->maxFileSize*1000 )
		{
			// Maximale Dateigroesse ueberschritten.
			// Der Status 507 "Zuwenig Speicherplatz" passt nicht ganz, aber fast :)
			$this->httpStatus('507 Insufficient Storage' );
		}
		elseif	( ! $this->request->exists() )
		{
			// Neue Datei anlegen
			if	( !$this->create )
			{
				Logger::warn('WEBDAV: Creation of files not allowed by configuration' );
				$this->httpStatus('405 Not Allowed' );
				return;
			}

            $folderid = $this->request->folderid;
			$this->client->fileAdd( $folderid,$this->request->basename,$this->data );
			$this->httpStatus('201 Created');
			return;
		}
		elseif	( $this->request->exists() )
		{
			// Bestehende Datei ueberschreiben.
			$id = $this->request->objectid;
            $this->client->fileWrite( $id,$this->data );

			$this->httpStatus('204 No Content');
			return;
		}
		elseif	( $this->request->type == URIParser::FOLDER )
		{
			Logger::error('PUT on folder is not supported, use PROPFIND. Lame client?' );
			$this->httpMethodNotAllowed();
		}
		else
		{
			// Fuer andere Objekttypen (Links, Seiten) ist kein PUT moeglich.
			Logger::warn('PUT only available for files. Pages and links are ignored' );
			$this->httpMethodNotAllowed();
		}
	}

	
}
