<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Fixtures;

use Digibit\Audit\Attributes\Audited;

/**
 * Example consumer-side audit row — demonstrates how a project built on top
 * of digibit/audit might persist entries produced from a ChangeSet.
 * Not part of the library's public API.
 */
class AuditLog
{
    private static int $AUTO_INCREMENT = 0;
    private static function nextId(): int { return ++self::$AUTO_INCREMENT; }

    private int $id;
    private string $entityType = '';
    private string|int|null $entityId = null;
    private string $eventAction = '';
    private ?string $field = null;
    private ?string $path = null;
    private ?string $oldValue = null;
    private ?string $newValue = null;
    private ?string $notes = null;
    private ?AuditLog $parent = null;
    private array $children;

    public function __construct()
    {
        $this->id = self::nextId();
        $this->children = [];
    }

    // ## Factory helpers

    public static function statusChange(
        object  $entity,
        string  $oldValue,
        string  $newValue,
        ?string $notes = null,
    ): self {
        $log        = self::fieldChange($entity, 'status', 'status', $oldValue, $newValue);
        $log->notes = $notes;
        return $log;
    }

    public static function action(
        object  $entity,
        string  $action,
        ?string $notes    = null,
        ?string $field    = null,
        ?string $oldValue = null,
        ?string $newValue = null,
    ): self {
        $log              = new self();
        $log->entityType  = (new \ReflectionClass($entity))->getShortName();
        $log->entityId    = Audited::idFor($entity);
        $log->eventAction = $action;
        $log->notes       = $notes;
        $log->field       = $field;
        $log->path        = $field;
        $log->oldValue    = $oldValue;
        $log->newValue    = $newValue;
        return $log;
    }

    public static function fieldChange(
        object $entity,
        string $field,
        string $path,
        string $oldValue,
        string $newValue,
    ): self {
        $log              = new self();
        $log->entityType  = (new \ReflectionClass($entity))->getShortName();
        $log->entityId    = Audited::idFor($entity);
        $log->eventAction = 'field_change';
        $log->field       = $field;
        $log->path        = $path;
        $log->oldValue    = $oldValue;
        $log->newValue    = $newValue;
        return $log;
    }

    public static function fieldAdded(
        object $entity,
        string $field,
        string $path,
        string $newValue,
    ): self {
        $log              = new self();
        $log->entityType  = (new \ReflectionClass($entity))->getShortName();
        $log->entityId    = Audited::idFor($entity);
        $log->eventAction = 'field_added';
        $log->field       = $field;
        $log->path        = $path;
        $log->newValue    = $newValue;
        return $log;
    }

    public static function fieldRemoved(
        object $entity,
        string $field,
        string $path,
        string $oldValue,
    ): self {
        $log              = new self();
        $log->entityType  = (new \ReflectionClass($entity))->getShortName();
        $log->entityId    = Audited::idFor($entity);
        $log->eventAction = 'field_removed';
        $log->field       = $field;
        $log->path        = $path;
        $log->oldValue    = $oldValue;
        return $log;
    }

    public static function nested(
        object $entity,
        string $field,
        string $path,
    ): self {
        $log              = new self();
        $log->entityType  = (new \ReflectionClass($entity))->getShortName();
        $log->entityId    = Audited::idFor($entity);
        $log->eventAction = 'nested';
        $log->field       = $field;
        $log->path        = $path;
        return $log;
    }

    // ## Getters

    public function getId(): string { return (string) $this->id; }
    public function getEntityType(): string { return $this->entityType; }
    public function getEntityId(): string|int|null { return $this->entityId; }
    public function getEventAction(): string { return $this->eventAction; }
    public function getField(): ?string { return $this->field; }
    public function getPath(): ?string { return $this->path; }
    public function getOldValue(): ?string { return $this->oldValue; }
    public function getNewValue(): ?string { return $this->newValue; }
    public function getNotes(): ?string { return $this->notes; }

    public function addChild(AuditLog $child): void
    {
        $child->parent = $this;
        $this->children[] = $child;
    }

    public function getParent(): ?AuditLog { return $this->parent; }

    /** @return array<int, AuditLog> */
    public function getChildren(): array { return $this->children; }

    public function isStatusChange(): bool { return $this->eventAction === 'status_change' || $this->field === 'status'; }
    public function isFieldChange(): bool  { return $this->eventAction === 'field_change' && $this->field !== 'status'; }
    public function isFieldAdded(): bool   { return $this->eventAction === 'field_added'; }
    public function isFieldRemoved(): bool { return $this->eventAction === 'field_removed'; }
    public function isNested(): bool       { return $this->eventAction === 'nested'; }
}
