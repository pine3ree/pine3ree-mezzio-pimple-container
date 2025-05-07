<?php

/**
 * @package pine3ree-mezzio-pimple-container
 * @author  pine3ree https://github.com/pine3ree (This "fork")
 */

declare(strict_types=1);

namespace pine3ree\Mezzio\Pimple\Exception;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException as PhpRuntimeException;
use Throwable;

class RuntimeException extends PhpRuntimeException implements ContainerExceptionInterface
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
