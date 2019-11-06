<?php

class DAV_MKCOL extends DAV
{

	/**
	 * Verzeichnis anlegen.
	 */		
	public function execute()
	{

		if	( $this->data )
		{
			$this->httpStatus('415 Unsupported Media Type' ); // Kein Body erlaubt
		}
		elseif	( $this->readonly )
		{
            Logger::trace('hi');
			$this->httpForbidden(); // Kein Schreibzugriff erlaubt
		}
		elseif	( $this->request->type == URIParser::PROJECT && ! $this->request->exists() )
		{
		    // Create a new empty project.
		    $this->client->projectAdd( $this->request->basename);
		}
        elseif	( $this->request->type == URIParser::FOLDER && ! $this->request->exists() )
        {
            // Create a new folder
            $this->client->folderAdd( $this->request->folderid, $this->request->basename );
            $this->httpStatus('201 Created');
        }
		elseif   ( $this->request->exists() )
		{
			// MKCOL ist nicht moeglich, wenn die URI schon existiert.
			Logger::warn('MKCOL-Request to an existing resource');
			$this->httpMethodNotAllowed();
		}
		else
		{
			Logger::warn('');
			throw new InvalidArgumentException();
		}
	}

}
