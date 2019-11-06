<?php

namespace dav\exception;

use Exception;

class CMSForbiddenError extends Exception
{
    public function __construct($message = "")
    {
        parent::__construct($message);
    }
}