<?php

namespace dav;

use cms\CMS;
use Exception;
use RuntimeException;

/**
 * Class URIParser.
 * Parsing a DAV url in the format "/<projectname>/<folder>/<folder>/<object>".
 */
class URIParser
{
    const ROOT    = 'root';
    const PROJECT = 'project';
    const FOLDER  = 'folder';

    public $type;
    public $projectid;
    public $objectid;
    public $folderid;
    public $basename;

    public $uri;
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

        $this->type = '';
        try {

            $result = $this->client->projectlist();
        }
        catch( Exception $e) {
            Logger::error("Failed to read projects: \n".$this->client->__toString()."\n".$e->getMessage() );
            throw $e;
        }


        $projects = $result['projects'];

        foreach( $projects as $id=>$projectinfo)
            if   ( $projectinfo['name'] == $projectName)
                $this->projectid = $id;

        if ( ! $this->projectid ) {
            $this->basename = $projectName;
            $this->type = self::PROJECT;
            return;
        }

        $project = $this->client->project($this->projectid);

        $objectid = $project['rootobjectid'];
        $folderid = $objectid;
        $type     = 'folder';
        $name     = $projectName;

        while (sizeof($uriParts) > 0) {
            $name = array_shift($uriParts);

            if   ( !$name )
                continue; // empty path segments

            $folder = $this->client->folder($objectid);
            $folderid = $objectid;

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
                $objectid = null;
                break;
            }
        }

        $this->type = $type;
        $this->folderid   = $folderid;
        $this->objectid   = $objectid;
        $this->basename   = $name;
    }


    public function isRoot() {

        return $this->type == self::ROOT;
    }

    public function exists() {
        return boolval($this->objectid);
    }



    /**
     * Representation of this URI.
     *
     * @return string
     */
    public function __toString()
    {
        return "DAV-URI: $this->uri ==> [$this->type] projectid: $this->projectid / objectid: $this->objectid folderid: $this->folderid name: $this->basename";
    }
}