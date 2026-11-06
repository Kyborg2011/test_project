<?php

declare(strict_types=1);

namespace App\Messaging\Exceptions;

use RuntimeException;

/**
 * The message cannot be processed in principle (malformed); retrying will not help.
 */
final class InvalidMessage extends RuntimeException
{
}
