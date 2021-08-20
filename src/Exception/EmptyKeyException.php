<?php

declare(strict_types=1);

namespace Bearman\FileCompressor\Exception;

use Exception;
use Throwable;

class EmptyKeyException extends Exception
{
    public function __construct(
        $message = "",
        $code = 0,
        Throwable $previous = null
    ) {
        $message = sprintf(
            'The key for %s API is not installed',
            $message
        );

        parent::__construct($message, $code, $previous);
    }
}
