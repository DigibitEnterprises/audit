<?php declare(strict_types=1);

namespace Digibit\Audit\Exception;

/**
 * Thrown when an object is passed somewhere the audit package requires an
 * #[Audited] class — snapshotting it via Snapshot::capture(), or resolving
 * its identity via Audited::idFor().
 */
final class NotAuditedException extends LogicException
{
}
