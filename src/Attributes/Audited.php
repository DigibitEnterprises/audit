<?php declare(strict_types=1);

namespace Digibit\Audit\Attributes;

use Attribute;
use Digibit\Audit\Exception\InvalidIdentityTypeException;
use Digibit\Audit\Exception\NotAuditedException;
use Digibit\Audit\Exception\UninitializedIdentityException;
use Digibit\Audit\Support\PropertyLocator;

#[Attribute(Attribute::TARGET_CLASS)]
class Audited
{
    /** @var array<class-string, ?self> */
    private static array $cache = [];

    public function __construct(
        public string $id = 'id',
    ) {}

    /**
     * Resolves the #[Audited] attribute instance for a class, if present,
     * caching per class-string so repeated calls (e.g. across every element
     * of a large collection) only pay reflection cost once per class.
     */
    public static function forClass(string $class): ?self
    {
        if (array_key_exists($class, self::$cache)) {
            return self::$cache[$class];
        }

        $attr = (new \ReflectionClass($class))->getAttributes(self::class)[0] ?? null;

        return self::$cache[$class] = $attr?->newInstance();
    }

    /**
     * Resolves the identity value for a #[Audited] instance, using
     * whichever property the attribute declares (default: 'id').
     */
    public static function idFor(object $entity): string|int
    {
        $class = $entity::class;
        $config = self::forClass($class);

        if ($config === null) {
            throw new NotAuditedException(
                "{$class} is not a #[Audited] — cannot resolve its identity."
            );
        }

        $idProp = PropertyLocator::find($class, $config->id);

        if (!$idProp->isInitialized($entity)) {
            throw new UninitializedIdentityException(
                "Audited id property '{$config->id}' on {$class} is uninitialized — "
                . 'the entity must be assigned an identity before it can be audited.'
            );
        }

        $value = $idProp->getValue($entity);

        if (!is_string($value) && !is_int($value)) {
            throw new InvalidIdentityTypeException(
                "Audited id property '{$config->id}' on {$class} "
                . 'must resolve to string or int, got ' . get_debug_type($value)
            );
        }

        return $value;
    }
}