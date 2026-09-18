<?php declare(strict_types=1);

namespace Digibit\Audit;

use Digibit\Audit\Attributes\Audited;
use Digibit\Audit\Diff\ChangeAction;
use Digibit\Audit\Diff\ChangeSet;
use Digibit\Audit\Support\Uuid;

/**
 * Immutable record of a single scalar-level change, produced by flattening
 * a ChangeSet. This is the shape the library defines and AuditStore persists;
 * how it maps to a storage schema is the consumer's concern.
 */
final class AuditEntry
{
    /**
     * @param array<string, mixed> $context Opaque metadata the application
     *     attached via ChangeRecorder::transaction()/record() — e.g. actor
     *     identity, request source. The audit package neither populates nor
     *     interprets these keys; it only carries them through from wherever
     *     the caller attached them to every entry produced in that scope.
     * @param string|int|null $collectionKey Raw element key when this entry
     *     represents a collection element change, queryable independent of
     *     how $path renders it. Null for scalar-property changes.
     * @param int|null $collectionSize Size of the owning collection *after*
     *     the mutation, when this entry represents a collection element
     *     change. Null otherwise.
     */
    public function __construct(
        public readonly string             $eventId,
        public readonly string             $transactionId,
        public readonly string             $entityClass,
        public readonly string|int         $entityId,
        public readonly string             $path,
        public readonly ChangeAction       $action,
        public readonly mixed              $oldValue,
        public readonly mixed              $newValue,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly array              $context = [],
        public readonly string|int|null    $collectionKey = null,
        public readonly ?int               $collectionSize = null,
    ) {}

    /**
     * Flattens a ChangeSet into one AuditEntry per leaf change,
     * recursing into nested ChangeSets (nested entities and collections).
     *
     * @param array<string, mixed> $context Carried through unchanged onto
     *     every entry produced from this ChangeSet, including recursed
     *     nested ones.
     * @return list<AuditEntry>
     */
    public static function fromChangeSet(
        object    $entity,
        ChangeSet $changes,
        string    $transactionId,
        array     $context = [],
    ): array {
        $entityClass = $entity::class;
        $entityId    = Audited::idFor($entity);
        $occurredAt  = new \DateTimeImmutable();
        $entries     = [];

        foreach ($changes->all() as $change) {
            if ($change->action === ChangeAction::Nested) {
                // recurse — same entity, $change->nested already carries
                // fully-qualified paths for everything below it
                array_push($entries, ...self::fromChangeSet(
                    $entity,
                    $change->nested,
                    $transactionId,
                    $context,
                ));
                continue;
            }

            $entries[] = new self(
                eventId:        Uuid::v4(),
                transactionId:  $transactionId,
                entityClass:    $entityClass,
                entityId:       $entityId,
                path:           $change->path,
                action:         $change->action,
                oldValue:       $change->original,
                newValue:       $change->modified,
                occurredAt:     $occurredAt,
                context:        $context,
                collectionKey:  $change->collectionKey,
                collectionSize: $change->collectionSize,
            );
        }

        return $entries;
    }

    /**
     * Builds a single AuditEntry for a discrete event with no before/after
     * value — e.g. "secret rotated" — bypassing Snapshot/ChangeSet entirely
     * so the value in question is never captured.
     *
     * @param array<string, mixed> $context
     */
    public static function forEvent(
        object $entity,
        string $eventName,
        string $transactionId,
        array  $context = [],
    ): self {
        return new self(
            eventId:       Uuid::v4(),
            transactionId: $transactionId,
            entityClass:   $entity::class,
            entityId:      Audited::idFor($entity),
            path:          $eventName,
            action:        ChangeAction::Occurred,
            oldValue:      null,
            newValue:      null,
            occurredAt:    new \DateTimeImmutable(),
            context:       $context,
        );
    }

    /**
     * Convenience discriminator string for consumers that want one, derived
     * from data this entry already carries — e.g. "order.lines.added" for a
     * collection element, "order.status.modified" for a scalar property.
     * Purely a default naming convention; consumers wanting a different
     * vocabulary can compose their own from entityClass/path/action/
     * collectionKey directly instead of using this.
     */
    public function eventType(): string
    {
        $entity = strtolower((new \ReflectionClass($this->entityClass))->getShortName());
        $scope  = $this->collectionKey !== null ? $this->collectionProperty() : $this->path;

        return "{$entity}.{$scope}.{$this->action->value}";
    }

    private function collectionProperty(): string
    {
        $bracket = strpos($this->path, '[');
        return $bracket === false ? $this->path : substr($this->path, 0, $bracket);
    }
}
