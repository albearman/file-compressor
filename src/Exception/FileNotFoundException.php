<?php

declare(strict_types=1);

namespace Bearman\FileCompressor\Exception;

use Exception;
use Throwable;

class FileNotFoundException extends Exception
{
    public function __construct(
        $message = "",
        $code = 0,
        Throwable $previous = null
    ) {
        $message = sprintf(
            'File "%s" not found',
            $message
        );

        parent::__construct($message, $code, $previous);
    }
}
