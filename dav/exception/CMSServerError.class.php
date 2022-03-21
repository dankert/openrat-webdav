<?php

namespace dav\exception;

use Exception;

class CMSServerError extends Exception
{
    public function __construct($message = "",$previous=null)
    {
        parent::__construct($message,0,$previous);
    }
}