<?php declare(strict_types=1);

namespace Digibit\Audit\Exception;

/** Thrown by Snapshot::captureCollection() when an #[AuditedCollection] contains a null element. */
final class NullCollectionElementException extends RuntimeException
{
}
