<?php declare(strict_types=1);

namespace Digibit\Audit\Exception;

/**
 * Thrown by PropertyValueResolver::resolve() when
 * PropertyValueReader::read() itself throws while evaluating a
 * #[AuditedProperty(valueExpr:)] expression.
 *
 * If the expression evaluates successfully but the extracted value
 * can't be captured (e.g. it's a Closure or unresolvable object), that
 * raises UncapturableValueException/UnresolvedValueException instead —
 * not this exception — since the failure is about the value, not the
 * expression.
 */
final class ExpressionException extends RuntimeException
{
}
