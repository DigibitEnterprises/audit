<?php declare(strict_types=1);

namespace Digibit\Audit\Exception;

/**
 * Thrown by PropertyValueResolver::resolve() when a #[AuditedProperty]
 * declares a valueExpr but the property's actual value isn't an object —
 * valueExpr is evaluated against the value via PropertyAccess, which
 * requires an object to read from.
 */
final class NonObjectPropertyException extends LogicException
{
}
