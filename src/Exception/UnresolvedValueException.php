<?php declare(strict_types=1);

namespace Digibit\Audit\Exception;

/**
 * Thrown when a value fell through every registered resolver with nothing
 * left able to claim it — a Traversable, a JsonSerializable, or any other
 * unrecognized object type. Unlike UncapturableValueException, the value
 * isn't inherently unauditable; it just has no resolver for it yet.
 *
 * If the value is genuinely a collection, capture it with
 * #[AuditedCollection] on the property instead of #[AuditedProperty] —
 * collections are diffed element-by-element and can never collapse to one
 * atomic value.
 *
 * Otherwise, register a resolver for the type via
 * Digibit\Audit\Snapshot\Resolver\PropertyValueResolver::register() /
 * registerFactory(), or reduce it to a resolvable value via a
 * #[AuditedProperty(valueExpr:)] expression.
 */
final class UnresolvedValueException extends ValueException
{
}
