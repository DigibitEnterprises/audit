<?php declare(strict_types=1);

namespace Digibit\Audit\Exception;

/**
 * Thrown when a value being captured for an audit snapshot has no
 * serializable representation at all — a Closure or a resource — regardless
 * of what resolvers are registered, custom or otherwise. See
 * UnresolvedValueException for the case where a value could in principle be
 * audited but nothing claimed it — registering a resolver is a valid fix
 * there, but not here.
 *
 * There's no resolver registration that fixes this. Exclude the offending
 * property from the audit surface, or, if it came from a
 * #[AuditedProperty(valueExpr:)] expression, rewrite the expression so it
 * never produces a Closure or resource.
 */
final class UncapturableValueException extends ValueException
{
}
