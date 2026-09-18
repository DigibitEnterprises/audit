<?php declare(strict_types=1);

namespace Digibit\Audit\Support;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class PropertyValueReader
{
    private static ?PropertyAccessorInterface $accessor = null;

    private static function accessor(): PropertyAccessorInterface
    {
        return self::$accessor ??= PropertyAccess::createPropertyAccessor();
    }

    public static function read(object $value, string $expression): mixed
    {
        return self::accessor()->getValue($value, $expression);
    }
}
