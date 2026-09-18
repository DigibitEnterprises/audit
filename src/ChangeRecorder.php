<?php declare(strict_types=1);

namespace Digibit\Audit;

use Digibit\Audit\Diff\ChangeSet;
use Digibit\Audit\Snapshot\Snapshot;
use Digibit\Audit\Support\Uuid;

class ChangeRecorder
{
    /**
     * Stack of open transaction scopes, each holding its own correlation ID
     * and whatever context the application attached when opening it (e.g.
     * actor identity, request source). The audit package never reads or
     * validates these keys — it only carries them through onto every
     * AuditEntry produced inside the scope. Who the actor is, what "source"
     * means, and so on are entirely the consuming application's concern.
     *
     * @var list<array{id: string, context: array<string, mixed>}>
     */
    private array $transactionStack = [];

    public function __construct(
        protected readonly AuditStore $store,
    ) {}

    /**
     * Starts a correlation scope. All record() calls inside share one
     * transaction ID and inherit $context unless overridden at the
     * individual record() call. Nested transaction() calls each get their
     * own ID — inner transactions are independent logical units, not merged
     * into outer.
     *
     * @param array<string, mixed> $context Opaque metadata to attach to every
     *     AuditEntry produced inside this scope — e.g.
     *     ['actor_id' => $user->id, 'source' => 'web_ui']. The audit package
     *     does not interpret these keys; the consuming application defines
     *     and reads them back out of AuditEntry::$context.
     */
    public function transaction(callable $work, array $context = []): mixed
    {
        $this->transactionStack[] = ['id' => Uuid::v4(), 'context' => $context];
        try {
            return $work();
        } finally {
            array_pop($this->transactionStack);
        }
    }

    /**
     * Snapshots $entity, runs $mutation, diffs before/after, and persists
     * any changes.
     *
     * Mutation callable contract:
     *   true              → proceed with diff and persist
     *   false             → abort recording silently
     *   string|\Throwable → abort recording with reason
     *
     * @param callable(): bool|string|\Throwable $mutation
     * @param array<string, mixed> $context Merged over the enclosing
     *     transaction()'s context — keys given here win on collision. Useful
     *     for attaching call-specific detail without opening a transaction
     *     just for one record() call.
     */
    public function record(object $entity, callable $mutation, array $context = []): RecorderResult
    {
        $before = Snapshot::capture($entity);
        $signal = $mutation();

        return match (true) {
            $signal === false                                    => RecorderResult::aborted(),
            is_string($signal) || $signal instanceof \Throwable => RecorderResult::aborted($signal),
            default                                              => $this->diffAndPersist($entity, $before, $context),
        };
    }

    /**
     * Diffs the entity against a previously-captured snapshot and persists
     * any changes. Protected for subclasses that take the snapshot at a
     * different point in time (e.g. FormChangeRecorder).
     *
     * @param array<string, mixed> $context Merged over the current
     *     transaction's context (call-site keys win on collision).
     */
    protected function diffAndPersist(object $entity, mixed $before, array $context = []): RecorderResult
    {
        $changes = ChangeSet::diff($before, Snapshot::capture($entity));

        if ($changes->isEmpty()) {
            return RecorderResult::unchanged();
        }

        $this->store->persist(
            AuditEntry::fromChangeSet(
                $entity,
                $changes,
                $this->currentTransactionId(),
                context: [...$this->currentContext(), ...$context],
            )
        );

        return RecorderResult::changed();
    }

    /**
     * Records a discrete event with no before/after value — e.g. "secret
     * rotated" — without snapshotting or diffing $entity at all, so the
     * value in question is never captured into an AuditEntry.
     *
     * @param array<string, mixed> $context Merged over the enclosing
     *     transaction()'s context — keys given here win on collision.
     */
    public function recordEvent(object $entity, string $eventName, array $context = []): void
    {
        $this->store->persist([
            AuditEntry::forEvent(
                $entity,
                $eventName,
                $this->currentTransactionId(),
                context: [...$this->currentContext(), ...$context],
            ),
        ]);
    }

    protected function currentTransactionId(): string
    {
        $frame = end($this->transactionStack);
        return $frame !== false ? $frame['id'] : Uuid::v4();
    }

    /** @return array<string, mixed> */
    protected function currentContext(): array
    {
        $frame = end($this->transactionStack);
        return $frame !== false ? $frame['context'] : [];
    }
}
