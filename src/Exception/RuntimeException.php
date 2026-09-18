<?php declare(strict_types=1);

namespace Digibit\Audit\Exception;

/** Base for audit exceptions that signal a failure only detectable at runtime. */
class RuntimeException extends \RuntimeException implements ExceptionInterface
{
}
