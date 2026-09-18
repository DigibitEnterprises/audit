# Digibit Audit

Attribute-driven snapshotting and diffing of PHP objects — capture the state
of an entity before and after a change, and produce a typed, nested `ChangeSet`
describing exactly what changed.

Useful for audit trails, activity logs, and change tracking on Doctrine
entities or plain PHP objects, without hand-writing diff logic per entity.

## Installation

```bash
composer require digibit/audit
```

Requires PHP 8.2+.

## Usage

### 1. Mark your entities as diffable

```php
use Digibit\Audit\Attributes\Audited;
use Digibit\Audit\Attributes\AuditedProperty;
use Digibit\Audit\Attributes\AuditedCollection;

#[Audited]
class Order
{
    #[AuditedProperty]
    public string $status;

    #[AuditedProperty(valueExpr: 'email')]
    public ?Customer $customer;

    #[AuditedCollection(ofType: OrderLine::class, keyBy: 'id')]
    public array $lines;
}

#[Audited]
class OrderLine
{
    public string $id;

    #[AuditedProperty]
    public int $quantity;
}
```

- `#[Audited]` marks a class as eligible for snapshotting. Accepts an
  optional `id` property name (default `'id'`) identifying which property holds
  the entity's identity value for audit entries.
- `#[AuditedProperty]` marks a property to include in the snapshot. Properties
  without this attribute are ignored entirely. The value is resolved
  according to its type:
  - Scalars (string, int, float, bool, null) are captured as-is.
  - `\UnitEnum`/`\BackedEnum` values are captured as their `->value` (backed)
    or `->name` (pure).
  - `\DateTimeInterface` values are captured as an ISO 8601 string.
  - `\DateInterval` values are captured as an ISO 8601 duration string.
  - Another `#[Audited]` entity is captured recursively as a nested snapshot
    — this is what makes `Order::$customer` in the example above diffable
    without any extra configuration.
  - `valueExpr` lets you resolve a value off a related object (via Symfony's
    `PropertyAccess`) instead of snapshotting the whole related entity — e.g.
    recording a customer's email rather than diffing the entire `Customer`.
    The extracted value is itself run back through these same rules (so it
    may be a scalar, enum, date, or another `#[Audited]` entity).
  - Anything else object-shaped (a plain non-audited object, a `\Closure`, a
    `\Traversable`, a `\JsonSerializable`) throws — see "Exceptions" below —
    rather than being silently dropped or serialized. You'll need
    `valueExpr`, `#[AuditedCollection]`, or a custom resolver (see below)
    instead.
  - You can register additional resolvers for other object types via
    `PropertyValueResolver::register(YourResolver::class, priority: ...)` or
    `::registerFactory(...)` for resolvers needing constructor arguments.
- `#[AuditedCollection]` marks a collection of diffable entities, keyed by
  one of their properties (`keyBy`, default `id`) so additions, removals, and
  per-item changes can be tracked across the collection. The `keyBy`
  property must be typed `string`, `int`, or `string|int` — this is
  validated when the attribute is constructed, so a mistyped key property
  fails fast rather than at snapshot time.

### 2. Implement `AuditStore`

`AuditStore` is the only interface your application needs to implement — it
receives a batch of `AuditEntry` records and persists them however your app
requires (a Doctrine entity, a log file, a message queue, etc.):

```php
use Digibit\Audit\AuditEntry;
use Digibit\Audit\AuditStore;

final class DatabaseAuditStore implements AuditStore
{
    public function persist(array $entries): void
    {
        foreach ($entries as $entry) {
            // $entry->transactionId  — correlates related changes
            // $entry->entityClass    — e.g. App\Entity\Order
            // $entry->entityId       — resolved from #[Audited(id: '...')]
            // $entry->path           — e.g. 'status', 'lines[id:42].quantity'
            // $entry->action         — ChangeAction enum: Added|Removed|Modified
            // $entry->oldValue       — value before (null for Added)
            // $entry->newValue       — value after (null for Removed)
            // $entry->occurredAt     — DateTimeImmutable at time of record()
            // $entry->context        — whatever your app attached, e.g. actor identity (see below)
        }
    }
}
```

Attributing a change to a user is your application's concern, not the
library's — the package has no notion of "actor" or "who's logged in". It
only carries whatever you attach via `$context` through to `AuditEntry::$context`
unread. There are two places to attach it:

**At the call site**, via `record()`'s/`transaction()`'s `$context` argument
— useful when different calls need different actors or extra call-specific
detail:

```php
use Symfony\Bundle\SecurityBundle\Security;

final class OrderService
{
    public function __construct(
        private readonly ChangeRecorder $recorder,
        private readonly Security $security,
    ) {}

    public function ship(Order $order): void
    {
        $this->recorder->record($order, function () use ($order): bool {
            $order->status = 'shipped';
            return true;
        }, context: [
            'actor_id' => $this->security->getUser()?->getUserIdentifier(),
        ]);
    }
}
```

**In the `AuditStore` implementation**, by injecting `Security` there instead
— useful when every entry in the app should be stamped with the current user
without every call site remembering to pass it:

```php
use Symfony\Bundle\SecurityBundle\Security;

final class DatabaseAuditStore implements AuditStore
{
    public function __construct(private readonly Security $security) {}

    public function persist(array $entries): void
    {
        $actorId = $this->security->getUser()?->getUserIdentifier();

        foreach ($entries as $entry) {
            // write $entry plus $actorId to your schema
        }
    }
}
```

### 3. Record changes via `ChangeRecorder`

`ChangeRecorder` takes the before/after snapshot, diff, and persist steps
out of your hands. Inject it and call `record()` around any mutation:

```php
use Digibit\Audit\ChangeRecorder;

// in your controller or service:
$result = $recorder->record($order, function () use ($order, $form, $request): bool {
    $form->handleRequest($request);
    if (!$form->isSubmitted() || !$form->isValid()) {
        return false; // abort: skip diffing and persisting entirely
    }
    $order->status = 'shipped';
    return true;
});
```

`record()` snapshots `$entity`, runs the mutation callable, and — depending
on what the callable returns — either diffs and persists the change or
aborts:

- `true` — proceed: diff the entity against its pre-mutation snapshot and
  persist any changes.
- `false` — abort silently: no diffing, no audit row, no error.
- `string` or `\Throwable` — abort with a reason, retained on the result for
  inspection/logging.

Any other return value (including other falsy values like `null` or `0`) is
treated the same as `true` and proceeds with diffing — only a literal `false`
aborts silently, so keep the callable's return type declared `bool` (or
`bool|string|\Throwable`) to avoid surprises.

`record()` returns a `RecorderResult`:

```php
use Digibit\Audit\RecorderResult;

// $result->aborted    — true if the callable returned false/string/Throwable
// $result->changed    — true if a diff was found and persisted
// $result->error      — the string|Throwable reason, if aborted with one; else null
// $result->successful() — !aborted && error === null (true for both "changed" and "unchanged, no diff")
```

### 4. Correlate related changes with `transaction()`

When one logical action touches multiple entities, wrap the calls in
`transaction()` so all the resulting `AuditEntry` records share one
`transactionId`:

```php
$recorder->transaction(function () use ($recorder, $load, $appointment) {
    $recorder->record($load, function () use ($load, $newCarrier): bool {
        $load->reassign($newCarrier);
        return true;
    });
    $recorder->record($appointment, function () use ($appointment): bool {
        $appointment->cancel();
        return true;
    });
});
```

`record()` calls outside any `transaction()` each get their own standalone
`transactionId`. Nested `transaction()` calls each get their own independent
ID — inner transactions are distinct logical units, not merged into the outer
one.

### 5. Record a discrete event with `recordEvent()`

Some actions are worth an audit entry but have no before/after value you'd
ever want captured — rotating a secret, sending a notification, triggering
an external sync. `recordEvent()` writes one `AuditEntry` with
`action: ChangeAction::Occurred` and `oldValue`/`newValue` both `null`,
without snapshotting or diffing the entity at all:

```php
$recorder->recordEvent($apiCredential, 'secret_rotated');
```

Because it never snapshots the entity, the value being rotated is never
read, held in memory, or captured into an entry — unlike `#[AuditedProperty]`,
there's no redaction step to configure or forget.

Like `record()`, it participates in the enclosing `transaction()`'s
correlation ID and context, and accepts its own `$context` merged over that:

```php
$recorder->recordEvent($apiCredential, 'secret_rotated', context: [
    'actor_id' => $this->security->getUser()?->getUserIdentifier(),
]);
```

### 6. Inspect the changes directly

You can also use `Snapshot` and `ChangeSet` directly without `ChangeRecorder`:

```php
use Digibit\Audit\Snapshot\Snapshot;
use Digibit\Audit\Diff\ChangeSet;

$before    = Snapshot::capture($order);
$order->status = 'shipped';
$changeSet = ChangeSet::diff($before, Snapshot::capture($order));

foreach ($changeSet->all() as $key => $change) {
    match ($change->action) {
        ChangeAction::Added    => /* $change->modified */,
        ChangeAction::Removed  => /* $change->original */,
        ChangeAction::Modified => /* $change->original, $change->modified */,
        ChangeAction::Nested   => /* recurse into $change->nested (a ChangeSet) */,
    };
}
```

### Capture and diff limits

- **Circular references.** If an `#[Audited]` entity graph references itself,
  directly or mutually, through `AuditedProperty`/`AuditedCollection`
  relations, `Snapshot::capture()` throws `CircularReferenceException`
  (see "Exceptions" below) rather than recursing until the stack overflows.
- **Max depth.** `ChangeSet::diff()` takes a `maxDepth` parameter (default
  `10`) and stops recursing into nested entities/collections past that
  depth, returning an empty `ChangeSet` for anything deeper. Pass a higher
  value if your entity graph is legitimately deeper than 10 levels.
- **Uninitialized properties.** A typed property that was never assigned a
  value is silently skipped from the snapshot rather than raising an error
  (PHP's own "must not be accessed before initialization" semantics still
  apply if you touch it elsewhere).

### Exceptions

Every exception this package throws lives under `Digibit\Audit\Exception\`,
implements `ExceptionInterface` (so you can catch that one type to handle any
of them), and extends either `LogicException` or `RuntimeException` from that
same namespace (themselves extending the matching SPL base):

- `NotAuditedException` — an object was passed where an `#[Audited]` class
  was required (snapshotting it, or resolving its identity).
- `CircularReferenceException` — see "Circular references" above.
- `NullCollectionElementException` — an `#[AuditedCollection]` contains a
  `null` element.
- `UninitializedIdentityException` — the entity's declared id property
  exists but was never assigned a value.
- `InvalidIdentityTypeException` — the entity's declared id property
  resolves to something other than `string|int`.
- `InvalidCollectionKeyException` — an `#[AuditedCollection]`'s `keyBy`
  property isn't typed `string`, `int`, or `string|int` on the target class;
  thrown at attribute-construction time, not snapshot time.
- `PropertyNotFoundException` — a named property doesn't exist anywhere in
  the class hierarchy.
- `NonObjectPropertyException` — a `valueExpr` was configured on a property
  whose actual value isn't an object.
- `ExpressionException` — `PropertyValueReader::read()` itself threw while
  evaluating a `valueExpr` (e.g. the expression references a property that
  doesn't exist). Carries the original throwable as `$previous`. If the
  expression evaluates fine but the *extracted* value can't be captured,
  that's `UncapturableValueException`/`UnresolvedValueException` instead,
  not this.
- `UncapturableValueException` — the value has no serializable
  representation at all (a `\Closure` or a resource); no resolver can fix
  this — exclude the property or rewrite the `valueExpr`.
- `UnresolvedValueException` — the value fell through every registered
  resolver unclaimed (a `\Traversable`, a `\JsonSerializable`, or any other
  unrecognized object type); register a resolver, use
  `#[AuditedCollection]` if it's really a collection, or reduce it via
  `valueExpr`.

`UncapturableValueException` and `UnresolvedValueException` both extend the
abstract `ValueException`, a common catch point for "this value just can't
go in a snapshot" as distinct from configuration errors like
`InvalidCollectionKeyException`.

## License

MIT
