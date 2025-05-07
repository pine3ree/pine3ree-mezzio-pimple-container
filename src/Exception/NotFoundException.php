<?php

/**
 * @package pine3ree-mezzio-pimple-container
 * @author  pine3ree https://github.com/pine3ree (This "fork")
 */

declare(strict_types=1);

namespace pine3ree\Mezzio\Pimple\Exception;

use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Throwable;

class NotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
