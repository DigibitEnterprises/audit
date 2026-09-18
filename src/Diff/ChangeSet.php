<?php declare(strict_types=1);

namespace Digibit\Audit\Diff;

use Digibit\Audit\Snapshot\CollectionSnapshot;
use Digibit\Audit\Snapshot\Snapshot;

final class ChangeSet
{
    /** @param array<string|int, Change> $changes */
    public function __construct(
        private readonly array $changes = [],
    ) {}

    public function isEmpty(): bool { return $this->changes === []; }

    /** @return array<string|int, Change> */
    public function all(): array { return $this->changes; }

    public function get(string|int $key): ?Change { return $this->changes[$key] ?? null; }

    public static function diff(
        ?Snapshot $before,
        ?Snapshot $after,
        int $maxDepth = 10,
        int $currentDepth = 0,
        string $path = '',
    ): ChangeSet {
        if ($currentDepth >= $maxDepth) {
            return new ChangeSet();
        }

        $original = $before?->entries() ?? [];
        $modified = $after?->entries() ?? [];

        $changes = [];
        $allKeys = array_unique([...array_keys($original), ...array_keys($modified)]);

        foreach ($allKeys as $key) {
            $inOriginal = array_key_exists($key, $original);
            $inModified = array_key_exists($key, $modified);
            $orig = $inOriginal ? $original[$key] : null;
            $mod  = $inModified ? $modified[$key] : null;
            $childPath = self::appendPath($path, $key, null);

            if ($inOriginal && !$inModified) {
                $changes[$key] = self::removedEntry($key, $childPath, $orig);
            } elseif (!$inOriginal && $inModified) {
                $changes[$key] = self::addedEntry($key, $childPath, $mod);
            } elseif ($orig instanceof CollectionSnapshot || $mod instanceof CollectionSnapshot) {
                $keyBy = ($orig ?? $mod)->keyBy();
                $nested = self::diffCollection($orig, $mod, $maxDepth, $currentDepth + 1, $childPath, $keyBy);
                if (!$nested->isEmpty()) {
                    $changes[$key] = Change::nested($key, $childPath, $nested);
                }
            } elseif ($orig instanceof Snapshot || $mod instanceof Snapshot) {
                $nested = self::diff($orig, $mod, $maxDepth, $currentDepth + 1, $childPath);
                if (!$nested->isEmpty()) {
                    $changes[$key] = Change::nested($key, $childPath, $nested);
                }
            } elseif ($orig !== $mod) {
                $changes[$key] = Change::modified($key, $childPath, $orig, $mod);
            }
        }

        return new ChangeSet($changes);
    }

    private static function diffCollection(
        ?CollectionSnapshot $before,
        ?CollectionSnapshot $after,
        int $maxDepth,
        int $currentDepth,
        string $path,
        string $keyBy,
    ): ChangeSet {
        if ($currentDepth >= $maxDepth) {
            return new ChangeSet();
        }

        $changes = [];
        $allKeys = array_unique([...($before?->keys() ?? []), ...($after?->keys() ?? [])]);

        // Size of the collection *after* the mutation, stamped onto every
        // element-level change below so a consumer can sanity-check the
        // resulting collection size without replaying the full history.
        $sizeAfter = $after !== null ? count($after->keys()) : 0;

        foreach ($allKeys as $key) {
            $b = $before?->get($key);
            $a = $after?->get($key);
            $elementPath = self::appendPath($path, $key, $keyBy);

            if ($b !== null && $a === null) {
                $changes[$key] = self::removedEntry($key, $elementPath, $b, collectionKey: $key, collectionSize: $sizeAfter);
            } elseif ($b === null && $a !== null) {
                $changes[$key] = self::addedEntry($key, $elementPath, $a, collectionKey: $key, collectionSize: $sizeAfter);
            } else {
                $nested = self::diff($b, $a, $maxDepth, $currentDepth + 1, $elementPath);
                if (!$nested->isEmpty()) {
                    $changes[$key] = Change::nested($key, $elementPath, $nested);
                }
            }
        }

        return new ChangeSet($changes);
    }

    private static function appendPath(string $path, string|int $key, ?string $keyBy): string {
        $segment = $keyBy !== null ? "[{$keyBy}:{$key}]" : (string) $key;
        return match (true) {
            $path === ''    => $segment,
            $keyBy !== null => $path . $segment,
            default         => "{$path}.{$segment}",
        };
    }

    private static function addedEntry(
        string|int $key,
        string $path,
        mixed $value,
        string|int|null $collectionKey = null,
        ?int $collectionSize = null,
    ): Change {
        return Change::added($key, $path, self::flatten($value), $collectionKey, $collectionSize);
    }

    private static function removedEntry(
        string|int $key,
        string $path,
        mixed $value,
        string|int|null $collectionKey = null,
        ?int $collectionSize = null,
    ): Change {
        return Change::removed($key, $path, self::flatten($value), $collectionKey, $collectionSize);
    }

    private static function flatten(mixed $value): mixed {
        return match (true) {
            $value instanceof Snapshot => $value->entries(),
            $value instanceof CollectionSnapshot => array_map(self::flatten(...), $value->all()),
            default => $value,
        };
    }
}