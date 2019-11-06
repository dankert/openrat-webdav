<?php

namespace dav\exception;

use Exception;

class CMSServerError extends Exception
{
    public function __construct($message = "")
    {
        parent::__construct($message);
    }
}