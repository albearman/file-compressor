<?php

declare(strict_types=1);

namespace Bearman\FileCompressor\Exception;

use Exception;

class ResponseNotFoundException extends Exception
{
    protected $message = 'Response not found';
}
