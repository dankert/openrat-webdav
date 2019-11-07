<?php

class DAV_GET extends DAV
{

	/**
	 * WebDav-GET-Methode.
	 * Die gew�nschte Datei wird geladen und im HTTP-Body mitgeliefert.
	 */	
	public function execute()
	{
		switch( $this->request->type )
		{
			case 'root':
			case 'folder':
				$this->getDirectoryIndex();
				break;
				
			case 'page':
				$this->httpStatus( '200 OK' );
				header('Content-Type: text/html');
			
				echo '<html><head><title>OpenRat WEBDAV Access</title></head>';
				echo '<body>';
				echo '<h1>'.$this->request->type.'</h1>';
				echo '<pre>';
				echo 'No Content available';
				echo '</pre>';
				echo '</body>';
				echo '</html>';
				break;
				
			case 'link':
				$this->httpStatus( '200 OK' );
			
				header('Content-Type: text/plain');
				
				$link = $this->client->link( $this->request->objectid );
				echo 'url: '      .$link['url']           ."\n";
				echo 'target-id: '.$link['linkedObjectId']."\n";
				
				break;
				
			case 'file':
			case 'image':
			case 'text':
				$this->httpStatus( '200 OK' );
				
				$file      = $this->client->file     ( $this->request->objectid );
				$filevalue = $this->client->filevalue( $this->request->objectid );
				
				header('Content-Type: '.$file['mimetype']);
				header('X-File-Id: '   .$this->request->objectid );
		
				// Angabe Content-Disposition
				// - Bild soll "inline" gezeigt werden
				// - Dateiname wird benutzt, wenn der Browser das Bild speichern moechte
				header('Content-Disposition: inline; filename='.$file['filename'].'.'.$file['extension'] );
				header('Content-Transfer-Encoding: binary' );
				header('Content-Description: '.$file['filename'].'.'.$file['extension'] );
		
				// Groesse des Bildes in Bytes
				// Der Browser hat so die Moeglichkeit, einen Fortschrittsbalken zu zeigen
				header('Content-Length: '.$file['size'] );
				
				echo $filevalue['value'];
				
				break;

            default:
                $this->httpStatus( '200 OK' );
                header('Content-Type: text/html');

                echo '<html><head><title>OpenRat WEBDAV Access</title></head>';
                echo '<body>';
                echo '<h1>'.$this->request->type.'</h1>';
                echo '<pre>';
                echo 'Unknown node type: '.$this->request->type;
                echo '</pre>';
                echo '</body>';
                echo '</html>';
                break;


        }
	}




    /**
     * Erzeugt ein Unix-�hnliche Ausgabe des Verzeichnisses als HTML.
     */
    protected function getDirectoryIndex()
    {
        $this->httpStatus( '200 OK' );

        // Verzeichnis ausgeben
        header('Content-Type: text/html');
        $nl = "\n";




        $titel = 'Index of '.htmlspecialchars($this->sitePath);
        $format = "%15s  %-19s  %-s\n";

        echo '<html><head><title>'.$titel.'</title></head>';
        echo '<body>';
        echo '<h1>'.$titel.'</h1>'.$nl;
        echo '<pre>';

        printf($format, "Size", "Last modified", "Filename");


        switch( $this->request->type )
        {
            case 'root':  // Projektliste

                $result = $this->client->projectlist();
                $projects = $result['projects'];
                foreach( $projects as $projectid=>$p )
                {
                    echo '<a href="'.$p['name'].'">'.$p['name'].'</a>'.$nl;
                }
                break;

            case 'folder':  // Verzeichnisinhalt

                $folder = $this->client->folder( $this->request->objectid );

                foreach( $folder['content']['object'] as $object )
                {

                    printf($format,
                        number_format(1),
                        strftime("%Y-%m-%d %H:%M:%S",$object['date'] ),
                        '<a href="./'.$object['filename'].'">'.$object['filename'].'</a>');
                    echo $nl;

                }
        }

        echo '</pre>';
        echo '</body>';
        echo '</html>';
    }




}
