<?php declare(strict_types=1);

namespace Digibit\Audit\Snapshot\Resolver;

use Digibit\Resolver\ResolverInterface;

final class EnumResolver implements ResolverInterface
{
    public static function supports(mixed $subject): bool
    {
        return $subject instanceof \UnitEnum;
    }

    public function resolve(mixed $subject): mixed
    {
        return $subject instanceof \BackedEnum ? $subject->value : $subject->name;
    }
}