<?php
/**
 * Translate plugin for Craft CMS 5.x
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\translate\errors;

use RuntimeException;
use Throwable;

/**
 * A provider API returned an error.
 *
 * The HTTP status is carried in the exception code so the retry logic can tell
 * a rate limit from a bad key without re-reading the response.
 */
class ApiException extends RuntimeException
{
    public function __construct(string $message, int $statusCode = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->getCode();
    }
}
