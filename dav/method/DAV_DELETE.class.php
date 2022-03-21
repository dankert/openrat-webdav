<?php

namespace dav\method;

use dav\DAV;
use dav\URIParser;

class DAV_DELETE extends DAV
{

		
	/**
	 * Deletion.
	 */		
	public function execute()
	{
		if	( $this->readonly )
		{
			$this->httpForbidden(); // Kein Schreibzugriff erlaubt
		}
		else
		{
			if	( ! $this->request->exists() )
			{
				// Nicht existente URIs kann man auch nicht loeschen.
				$this->httpStatus('404 Not Found' );
			}
			else
			{
			    switch( $this->request->type )
                {
                    case URIParser::ROOT:
                        $this->httpForbidden();
                        break;

                    case URIParser::PROJECT:
                        // Dangerous - but possible.
                        $this->client->projectDelete($this->request->projectid);
                        break;

                    case URIParser::FOLDER:
                        $this->client->folderDelete($this->request->objectid);
                        break;

                    case 'page':
                        $this->client->pageDelete($this->request->objectid);
                        break;

                    case 'file':
                        $this->client->fileDelete($this->request->objectid);
                        break;

                    case 'image':
                        $this->client->imageDelete($this->request->objectid);
                        break;

                    case 'text':
                        $this->client->textDelete($this->request->objectid);
                        break;

                    default:
                        $this->httpForbidden();
                }
			}

		}
	}

}
