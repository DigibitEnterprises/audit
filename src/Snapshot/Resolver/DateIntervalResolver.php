<?php declare(strict_types=1);

namespace Digibit\Audit\Snapshot\Resolver;

use Digibit\Resolver\ResolverInterface;

final class DateIntervalResolver implements ResolverInterface
{
    public static function supports(mixed $subject): bool
    {
        return $subject instanceof \DateInterval;
    }

    public function resolve(mixed $subject): mixed
    {
        return $subject->format('%RP%yY%mM%dDT%hH%iM%sS'); // ISO 8601 duration, %R always shows a sign
    }
}