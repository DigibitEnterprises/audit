<?php declare(strict_types=1);

namespace Digibit\Audit\Exception;

/** Common parent for property values that can't be captured into a snapshot. */
abstract class ValueException extends LogicException
{
}
