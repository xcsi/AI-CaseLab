<?php

namespace App\Exceptions;

use RuntimeException;

class ReattemptNotAllowedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This incident does not allow reattempts.');
    }
}
