<?php declare(strict_types=1);

namespace Digibit\Audit\Attributes;

use Attribute;
use Digibit\Audit\Exception\InvalidCollectionKeyException;
use Digibit\Audit\Support\PropertyLocator;

#[Attribute(Attribute::TARGET_PROPERTY)]
class AuditedCollection {
    private \ReflectionProperty $keyProperty;

    public function __construct(
        public string $ofType,
        public string $keyBy = 'id',
    ) {
        $prop = PropertyLocator::find($ofType, $keyBy);
        $type = $prop->getType();

        if (!$this->isStringOrIntType($type)) {
            throw new InvalidCollectionKeyException(
                "AuditedCollection key '{$keyBy}' on {$ofType} must be typed string or int, got "
                . ($type instanceof \ReflectionType ? (string) $type : 'no type')
            );
        }

        $this->keyProperty = $prop;
    }

    private function isStringOrIntType(?\ReflectionType $type): bool {
        if ($type === null) {
            return false; // untyped property — reject, can't verify at construct time
        }

        if ($type instanceof \ReflectionNamedType) {
            return in_array($type->getName(), ['string', 'int'], true);
        }

        if ($type instanceof \ReflectionUnionType) {
            // allow e.g. int|string, but reject anything with a third type in the union
            foreach ($type->getTypes() as $t) {
                if (!$t instanceof \ReflectionNamedType || !in_array($t->getName(), ['string', 'int'], true)) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }

    /**
     * Resolves the diff key for a collection element.
     *
     * An element that hasn't been assigned a real identity yet (e.g. a newly
     * added Doctrine entity before flush) can never have appeared in an
     * earlier snapshot under any key, so it's safe to key it by its PHP
     * object identity instead — the diff will correctly see it as newly
     * added without requiring persistence first. The "\0new:" prefix keeps
     * this synthetic key out of the real string|int key space so it can
     * never collide with an actual persisted identity.
     *
     * Relies on $object staying alive for the duration of one diff — this
     * holds naturally in normal use, since the owning collection keeps every
     * element referenced for as long as Snapshot::capture() is iterating it.
     */
    public function getKeyValue(object $object): string|int {
        if (!$this->keyProperty->isInitialized($object)) {
            return "\0new:" . spl_object_id($object);
        }

        $value = $this->keyProperty->getValue($object);

        if ($value === null) {
            return "\0new:" . spl_object_id($object);
        }

        return $value;
    }
}