<?php declare(strict_types=1);

namespace Digibit\Audit\Snapshot\Resolver;

use Digibit\Resolver\ResolverInterface;

final class DateTimeResolver implements ResolverInterface
{
    public static function supports(mixed $subject): bool
    {
        return $subject instanceof \DateTimeInterface;
    }

    public function resolve(mixed $subject): mixed
    {
        return $subject->format(\DateTimeInterface::ATOM);
    }
}