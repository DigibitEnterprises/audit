<?php declare(strict_types=1);

namespace Digibit\Audit\Exception;

/**
 * Thrown by Audited::idFor() when the entity's declared id property resolves
 * to a value that isn't string|int.
 */
final class InvalidIdentityTypeException extends LogicException
{
}
