<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Snapshot;

use Digibit\Audit\Exception\CircularReferenceException;
use Digibit\Audit\Snapshot\CollectionSnapshot;
use Digibit\Audit\Snapshot\Snapshot;
use Digibit\Audit\Tests\Fixtures\CircularA;
use Digibit\Audit\Tests\Fixtures\CircularB;
use Digibit\Audit\Tests\Fixtures\Customer;
use Digibit\Audit\Tests\Fixtures\LineHolder;
use Digibit\Audit\Tests\Fixtures\NotAudited;
use Digibit\Audit\Tests\Fixtures\NullableKeyItem;
use Digibit\Audit\Tests\Fixtures\Order;
use Digibit\Audit\Tests\Fixtures\OrderLine;
use Digibit\Audit\Tests\Fixtures\SelfReferencing;
use Digibit\Audit\Tests\Fixtures\UninitializedPropertyEntity;
use PHPUnit\Framework\TestCase;

final class SnapshotTest extends TestCase
{
    public function testCaptureOfNullReturnsNull(): void
    {
        self::assertNull(Snapshot::capture(null));
    }

    public function testCaptureThrowsForNonAuditedClass(): void
    {
        $this->expectException(\Digibit\Audit\Exception\NotAuditedException::class);
        $this->expectExceptionMessage('non-audited object');

        Snapshot::capture(new NotAudited());
    }

    public function testCaptureOnlyIncludesAuditedProperties(): void
    {
        $order = new Order(status: 'pending', internalNotes: 'ignore me');

        $snapshot = Snapshot::capture($order);

        self::assertNotNull($snapshot);
        $entries = $snapshot->entries();

        self::assertArrayHasKey('status', $entries);
        self::assertSame('pending', $entries['status']);
        self::assertArrayNotHasKey('internalNotes', $entries);
    }

    public function testCaptureSkipsUninitializedProperties(): void
    {
        $entity = new UninitializedPropertyEntity();

        $snapshot = Snapshot::capture($entity);

        self::assertNotNull($snapshot);
        self::assertArrayNotHasKey('lazy', $snapshot->entries());
    }

    public function testCaptureResolvesValueExprInsteadOfNestingEntity(): void
    {
        $order = new Order(status: 'pending', customer: new Customer('a@example.com'));

        $snapshot = Snapshot::capture($order);
        self::assertNotNull($snapshot);
        $entries = $snapshot->entries();

        self::assertSame('a@example.com', $entries['customer']);
    }

    public function testCaptureHandlesNullNestedRelation(): void
    {
        $order = new Order(status: 'pending', customer: null);

        $snapshot = Snapshot::capture($order);
        self::assertNotNull($snapshot);
        $entries = $snapshot->entries();

        self::assertNull($entries['customer']);
    }

    public function testCaptureNestsPlainAuditedObjectWithoutValueExpr(): void
    {
        $line = new OrderLine(id: 'line-1', quantity: 3);
        $order = new Order(status: 'pending', lines: [$line]);

        $snapshot = Snapshot::capture($order);
        self::assertNotNull($snapshot);
        $entries = $snapshot->entries();

        self::assertInstanceOf(CollectionSnapshot::class, $entries['lines']);
    }

    public function testCaptureCollectionKeysElementsByConfiguredProperty(): void
    {
        $lineA = new OrderLine(id: 'a', quantity: 1);
        $lineB = new OrderLine(id: 'b', quantity: 2);
        $order = new Order(status: 'pending', lines: [$lineA, $lineB]);

        $snapshot = Snapshot::capture($order);
        self::assertNotNull($snapshot);

        $collection = $snapshot->entries()['lines'];
        self::assertInstanceOf(CollectionSnapshot::class, $collection);

        self::assertSame(['a', 'b'], $collection->keys());
        self::assertTrue($collection->has('a'));
        self::assertSame(1, $collection->get('a')?->entries()['quantity']);
        self::assertNull($collection->get('missing'));
    }

    public function testCaptureCollectionSynthesizesKeyForNewlyAddedElementWithNoIdentityYet(): void
    {
        // Reproduces the scenario where an entity is added to an audited
        // collection and diffed before persistence has assigned it a real
        // id (e.g. FormChangeRecorder diffing before EntityManager::flush()).
        // This must not throw — the element has never appeared in any prior
        // snapshot under any key, so it's unambiguously new either way.
        $line   = new NullableKeyItem(id: null, quantity: 4);
        $holder = new LineHolder(id: 1, lines: [$line]);

        $snapshot = Snapshot::capture($holder);

        self::assertNotNull($snapshot);
        $collection = $snapshot->entries()['lines'];
        self::assertInstanceOf(CollectionSnapshot::class, $collection);
        self::assertCount(1, $collection->keys());
        self::assertSame(4, $collection->all()[$collection->keys()[0]]->entries()['quantity']);
    }

    public function testCaptureThrowsOnNullCollectionElement(): void
    {
        $order = new Order(status: 'pending', lines: [new OrderLine('a', 1), null]);

        $this->expectException(\Digibit\Audit\Exception\NullCollectionElementException::class);
        $this->expectExceptionMessage('Null element found in');

        Snapshot::capture($order);
    }

    public function testCaptureThrowsOnDirectSelfReference(): void
    {
        $entity = new SelfReferencing();
        $entity->self = $entity;

        $this->expectException(CircularReferenceException::class);
        $this->expectExceptionMessage('Circular reference detected');

        Snapshot::capture($entity);
    }

    public function testCaptureThrowsOnMutualCircularReference(): void
    {
        $a = new CircularA();
        $b = new CircularB();
        $a->partner = $b;
        $b->partner = $a;

        $this->expectException(CircularReferenceException::class);
        $this->expectExceptionMessage('Circular reference detected');

        Snapshot::capture($a);
    }

    public function testCaptureAllowsRevisitingTheSameEntityAfterItFinishes(): void
    {
        // a diamond reference (two properties pointing at the *same* non-circular
        // sub-entity) must not trip the cycle guard — only active re-entrancy should.
        $shared = new Customer('shared@example.com');
        $order  = new Order(status: 'pending', customer: $shared);

        $snapshot = Snapshot::capture($order);

        self::assertNotNull($snapshot);
        self::assertSame('shared@example.com', $snapshot->entries()['customer']);

        // capturing the same (already-finished) entity again afterward must still work
        $again = Snapshot::capture($order);
        self::assertNotNull($again);
    }
}
