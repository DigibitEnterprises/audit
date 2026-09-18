<?php declare(strict_types=1);

namespace Digibit\Audit\Snapshot\Resolver;

use Digibit\Audit\Attributes\Audited;
use Digibit\Audit\Snapshot\Snapshot;
use Digibit\Resolver\ResolverInterface;

final class AuditedEntityResolver implements ResolverInterface
{
    public static function supports(mixed $subject): bool
    {
        return is_object($subject) && Audited::forClass($subject::class) !== null;
    }

    public function resolve(mixed $subject): ?Snapshot
    {
        return Snapshot::capture($subject); // mutual recursion with Snapshot::capture() — intentional
    }
}