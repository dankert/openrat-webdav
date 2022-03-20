
WebDAV for OpenRat CMS
===

About
---

This is a WebDAV-Client for the [OpenRat Content Management System](http://www.openrat.de).

OpenRat is a statifying web content management system.

This client makes it possible for file browsers to access the virtual CMS directories. This makes it easy to exchange files and folders. 
 
**The virtual CMS file system is accessable via a standard DAV client**

Installation
---
- Edit the file dav.ini for your needs.
- point your DAV client to the dav.php

What is WebDAV?
--- 
See [wikipedia.org - WebDAV](https://en.wikipedia.org/wiki/WebDAV)

WebDAV is specified in [RFC 2518](http://www.ietf.org/rfc/rfc2518.txt).

Implementation details
---
Implemented is DAV level 1 (means: no locks).

This client is using the API from OpenRat CMS.

Following impliciments:
- Login only with username/password
- Only 1 database connection
- No page editing

# Docker

The WEBDAV interface is able to run in a docker machine.

## Build the docker image

First you have to build the docker image

    docker build . -t openrat-webdav

## Run the docker image

    docker run -P -t openrat-webdav

The DAV interface is now available on http://localhost:8080

Feel free to specify some parameters like the CMS Host and Port:

    docker run -P -t openrat-webdav -e "CMS_HOST=localhost -e "CMS_PORT=8000"

