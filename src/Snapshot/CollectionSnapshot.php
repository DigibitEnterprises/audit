<?php declare(strict_types=1);

namespace Digibit\Audit\Snapshot;

final class CollectionSnapshot {
    /** @var Snapshot[] keyed by element key */
    private array $elements = [];

    function __construct(
        private string $keyBy,
    ) {}

    public function add(string|int $key, Snapshot $element): void {
        $this->elements[$key] = $element;
    }

    public function has(string|int $key): bool {
        return isset($this->elements[$key]);
    }

    public function get(string|int $key): ?Snapshot {
        return $this->elements[$key] ?? null;
    }

    public function keys(): array {
        return array_keys($this->elements);
    }

    public function all(): array {
        return $this->elements;
    }

    public function keyBy(): string {
        return $this->keyBy;
    }
}