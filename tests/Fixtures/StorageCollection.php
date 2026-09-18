<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Fixtures;

/**
 * Minimal in-memory sink used by AuditLogger in tests — stands in for
 * whatever real persistence layer a consuming project would use.
 */
class StorageCollection
{
    private array $elements = [];

    public function add(mixed $element): void
    {
        $this->elements[] = $element;
    }

    /** @return array<int, mixed> */
    public function all(): array
    {
        return $this->elements;
    }
}
