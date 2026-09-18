<?php declare(strict_types=1);

namespace Digibit\Audit\Exception;

/**
 * Marker implemented by every exception this package throws, so consumers
 * can catch \Digibit\Audit\Exception\ExceptionInterface to handle any of
 * them regardless of which SPL base (LogicException/RuntimeException) it
 * extends.
 */
interface ExceptionInterface extends \Throwable
{
}
