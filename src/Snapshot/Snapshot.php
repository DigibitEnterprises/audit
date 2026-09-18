<?php declare(strict_types=1);

namespace Digibit\Audit\Snapshot;

use Digibit\Audit\Attributes\AuditedCollection;
use Digibit\Audit\Attributes\Audited;
use Digibit\Audit\Attributes\AuditedProperty;
use Digibit\Audit\Exception\CircularReferenceException;
use Digibit\Audit\Exception\NotAuditedException;
use Digibit\Audit\Exception\NullCollectionElementException;
use Digibit\Audit\Snapshot\Resolver\PropertyValueResolver;

final class Snapshot
{
    /**
     * Tracks objects currently being captured on this call stack, keyed by
     * spl_object_id, so a circular #[Audited] reference graph (mutual or
     * self-referential AuditedProperty/AuditedCollection relations) fails
     * fast instead of recursing until the PHP call stack overflows.
     *
     * @var array<int, true>
     */
    private static array $visiting = [];

    private function __construct(private array $snapshot) {}

    public static function capture(?object $entity): ?Snapshot
    {
        if ($entity === null) {
            return null; // handles nullable relations
        }

        $className = $entity::class;
        if (Audited::forClass($className) === null) {
            throw new NotAuditedException("found non-audited object {$className}");
        }

        $id = spl_object_id($entity);
        if (isset(self::$visiting[$id])) {
            throw new CircularReferenceException(
                "Circular reference detected while capturing {$className} — "
                . 'an #[Audited] entity graph must not reference itself through '
                . 'AuditedProperty/AuditedCollection relations.'
            );
        }
        self::$visiting[$id] = true;

        try {
            $snapshot = [];

            $reflClass = new \ReflectionClass($entity);
            foreach ($reflClass->getProperties() as $prop) {
                if ($prop->isStatic()) {
                    continue;
                }

                $collectionAttr = $prop->getAttributes(AuditedCollection::class)[0] ?? null;
                $propertyAttr   = $prop->getAttributes(AuditedProperty::class)[0] ?? null;

                if ($collectionAttr === null && $propertyAttr === null) {
                    continue;
                }

                if (!$prop->isInitialized($entity)) {
                    continue;
                }

                $propName = $prop->getName();
                $value = $prop->getValue($entity);

                if ($collectionAttr !== null) {
                    $snapshot[$propName] = self::captureCollection(
                        $value,
                        $collectionAttr->newInstance(),
                        $className,
                        $propName,
                    );
                    continue;
                }

                $snapshot[$propName] = PropertyValueResolver::resolve($value, $propertyAttr->newInstance()->valueExpr);
            }

            return new self($snapshot);
        } finally {
            unset(self::$visiting[$id]);
        }
    }

    private static function captureCollection(
        iterable $collection,
        AuditedCollection $config,
        string $ownerClass,
        string $ownerProperty,
    ): CollectionSnapshot {
        $out = new CollectionSnapshot($config->keyBy);
        foreach ($collection as $element) {
            if ($element === null) {
                throw new NullCollectionElementException(
                    "Null element found in {$ownerClass}::\${$ownerProperty} (AuditedCollection of {$config->ofType}) — "
                    . 'a typed Collection must not contain null elements.'
                );
            }
            $out->add($config->getKeyValue($element), self::capture($element));
        }
        return $out;
    }

    public function entries(): array
    {
        return $this->snapshot;
    }
}