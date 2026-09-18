<?php declare(strict_types=1);

namespace Digibit\Audit\Diff;

/**
 * A single, typed diff entry for one key.
 *
 * @template T
 */
final class Change
{
    /**
     * @param T|null $original
     * @param T|null $modified
     * @param string|int|null $collectionKey Raw element key when this change
     *     represents a collection element (as opposed to a scalar property
     *     or a nested entity), independent of how $path renders it. Lets a
     *     consumer query/index by element identity directly rather than
     *     parsing $path. Null for non-collection changes.
     * @param int|null $collectionSize Size of the owning collection *after*
     *     the mutation, when this change represents a collection element.
     *     Null for non-collection changes.
     */
    private function __construct(
        public readonly string|int      $key,
        public readonly string          $path,
        public readonly ChangeAction    $action,
        public readonly mixed           $original = null,
        public readonly mixed           $modified = null,
        public readonly ?ChangeSet      $nested = null,
        public readonly string|int|null $collectionKey = null,
        public readonly ?int            $collectionSize = null,
    ) {}

    public static function added(
        string|int $key,
        string $path,
        mixed $modified,
        string|int|null $collectionKey = null,
        ?int $collectionSize = null,
    ): self {
        return new self(
            $key,
            $path,
            ChangeAction::Added,
            modified: $modified,
            collectionKey: $collectionKey,
            collectionSize: $collectionSize,
        );
    }

    public static function removed(
        string|int $key,
        string $path,
        mixed $original,
        string|int|null $collectionKey = null,
        ?int $collectionSize = null,
    ): self {
        return new self(
            $key,
            $path,
            ChangeAction::Removed,
            original: $original,
            collectionKey: $collectionKey,
            collectionSize: $collectionSize,
        );
    }

    public static function modified(
        string|int $key,
        string $path,
        mixed $original,
        mixed $modified,
    ): self {
        return new self(
            $key,
            $path,
            ChangeAction::Modified,
            original: $original,
            modified: $modified,
        );
    }

    public static function nested(
        string|int $key,
        string $path,
        ChangeSet $nested,
    ): self {
        return new self(
            $key,
            $path,
            ChangeAction::Nested,
            nested: $nested,
        );
    }
}
