<?php declare(strict_types=1);

namespace Digibit\Audit\Exception;

/** Base for audit exceptions that signal a programming/configuration error. */
class LogicException extends \LogicException implements ExceptionInterface
{
}
