<?php declare(strict_types=1);

namespace Digibit\Audit\Exception;

/**
 * Thrown by the #[AuditedCollection] constructor when the property named by
 * keyBy isn't typed string|int (or a string|int union) on the target class.
 */
final class InvalidCollectionKeyException extends LogicException
{
}
