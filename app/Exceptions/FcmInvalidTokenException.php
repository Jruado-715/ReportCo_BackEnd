<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when FCM reports that a device token is invalid or no longer
 * registered (app uninstalled, token rotated, etc). This is distinct
 * from a transient delivery failure: retrying the same token will
 * never succeed, so the caller should stop using it instead of
 * burning retry attempts.
 */
class FcmInvalidTokenException extends RuntimeException
{
}
