<?php


use dav\exception\NotFoundException;

class URIParser
{
    const ROOT = 'root';

    public $type;
    public $projectid;
    public $objectid;

    private $uri;
    /**
     * @var CMS
     */
    private $client;

    public function __construct( $client, $uri )
    {
        $this->uri = $uri;
        $this->client = $client;
        $this->parseURI();
    }

    /**
     * URI parsen.
     */
    private function parseURI()
    {
        $uri = $this->uri;

        Logger::trace('WEBDAV: Parsen der URI ' . $uri);

        $uriParts = explode('/', $uri);

        $first = array_shift($uriParts);
        if ($first) {
            throw new RuntimeException( 'URI does not begin with \'/\'.' );
        }

        $projectName = array_shift($uriParts);

        if ( ! $projectName ) {
            $this->type = self::ROOT;  // Root-Verzeichnis
            return;
        }

        $result = $this->client->projectlist();
        //Logger::trace( print_r( $result,true) );

        $projects = $result['projects'];

        foreach( $projects as $id=>$projectinfo)
            if   ( $projectinfo['name'] == $projectName)
                $this->projectid = $id;

        if ( ! $this->projectid ) {
            throw new RuntimeException( 'Project \''.$projectName.'\' not found.' );
        }

        $project = $this->client->project($this->projectid);
        Logger::trace( print_r( $project,true) );


        $objectid = $project['rootobjectid'];
        $type = 'folder';

        while (sizeof($uriParts) > 0) {
            $name = array_shift($uriParts);

            if   ( !$name )
                continue; // empty path segments

            $folder = $this->client->folder($objectid);

            $found = false;
            foreach ($folder['content']['object'] as $oid => $object) {
                if ($object['filename'] == $name) {
                    $found = true;

                    $type     = $object['type'];
                    $objectid = $object['id'  ];

                    break;
                }

            }

            if (!$found) {
                throw new NotFoundException('Not found path segment: '.$name );
            }
        }

        $this->type = $type;
        $this->objectid   = $objectid;
    }


    public function __toString()
    {
        return "DAV-Object: $this->uri ==> [$this->type] projectid: $this->projectid / objectid: $this->objectid";
    }
}