<?php

declare(strict_types=1);

namespace Bearman\FileCompressor\Exception;

use Exception;

class ModeNotSupportException extends Exception
{
    protected $message = 'Mode compression not support';
}
