<?php declare(strict_types=1);

namespace Digibit\Audit\Exception;

/**
 * Thrown by Snapshot::capture() when an #[Audited] entity graph references
 * itself — mutually or self-referentially — through AuditedProperty/
 * AuditedCollection relations, which would otherwise recurse until the PHP
 * call stack overflows.
 */
final class CircularReferenceException extends LogicException
{
}
