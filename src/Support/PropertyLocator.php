<?php declare(strict_types=1);

namespace Digibit\Audit\Support;

use Digibit\Audit\Exception\PropertyNotFoundException;

/**
 * Reflects a named property on the class that actually declares it, walking
 * up the hierarchy as needed.
 *
 * `new \ReflectionProperty($class, $name)` only sees properties declared
 * directly on $class, or inherited as public/protected — a private property
 * declared on an ancestor (e.g. a shared AbstractEntity::$id) is invisible
 * to reflection through a subclass name, even though the subclass truly has
 * that property. Reflecting from the exact declaring class resolves
 * visibility correctly either way.
 */
final class PropertyLocator
{
    public static function find(string $class, string $name): \ReflectionProperty
    {
        for ($c = $class; $c !== false; $c = get_parent_class($c)) {
            if ((new \ReflectionClass($c))->hasProperty($name)) {
                return new \ReflectionProperty($c, $name);
            }
        }

        throw new PropertyNotFoundException("Property {$class}::\${$name} does not exist");
    }
}
