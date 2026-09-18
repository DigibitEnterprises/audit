<?php declare(strict_types=1);

namespace Digibit\Audit\Exception;

/**
 * Thrown by Audited::idFor() when the entity's declared id property exists
 * but has never been assigned a value.
 */
final class UninitializedIdentityException extends LogicException
{
}
