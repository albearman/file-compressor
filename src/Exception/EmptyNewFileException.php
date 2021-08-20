<?php

declare(strict_types=1);

namespace Bearman\FileCompressor\Exception;

use Exception;

class EmptyNewFileException extends Exception
{
    protected $message = 'You must specify the path to the new file';
}
