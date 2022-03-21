<?php

namespace dav\exception;

use Exception;

class CMSForbiddenError extends Exception
{
    public function __construct($message = "",$previous=null)
    {
        parent::__construct($message,0,$previous);
    }
}