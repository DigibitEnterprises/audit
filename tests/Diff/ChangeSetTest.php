<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Diff;

use Digibit\Audit\Diff\ChangeAction;
use Digibit\Audit\Diff\ChangeSet;
use Digibit\Audit\Snapshot\Snapshot;
use Digibit\Audit\Tests\Fixtures\Customer;
use Digibit\Audit\Tests\Fixtures\LineHolder;
use Digibit\Audit\Tests\Fixtures\NullableKeyItem;
use Digibit\Audit\Tests\Fixtures\Order;
use Digibit\Audit\Tests\Fixtures\OrderLine;
use PHPUnit\Framework\TestCase;

final class ChangeSetTest extends TestCase
{
    public function testDiffOfIdenticalSnapshotsIsEmpty(): void
    {
        $order = new Order(status: 'pending');
        $before = Snapshot::capture($order);
        $after = Snapshot::capture($order);

        $changeSet = ChangeSet::diff($before, $after);

        self::assertTrue($changeSet->isEmpty());
        self::assertSame([], $changeSet->all());
    }

    public function testDiffOfBothNullIsEmpty(): void
    {
        $changeSet = ChangeSet::diff(null, null);

        self::assertTrue($changeSet->isEmpty());
    }

    public function testModifiedScalarProperty(): void
    {
        $before = Snapshot::capture(new Order(status: 'pending'));
        $after = Snapshot::capture(new Order(status: 'shipped'));

        $changeSet = ChangeSet::diff($before, $after);

        $change = $changeSet->get('status');
        self::assertNotNull($change);
        self::assertSame(ChangeAction::Modified, $change->action);
        self::assertSame('pending', $change->original);
        self::assertSame('shipped', $change->modified);
    }

    public function testGetReturnsNullForUnchangedKey(): void
    {
        $before = Snapshot::capture(new Order(status: 'pending'));
        $after = Snapshot::capture(new Order(status: 'shipped'));

        $changeSet = ChangeSet::diff($before, $after);

        self::assertNull($changeSet->get('customer'));
    }

    public function testAddedKeyWhenBeforeSnapshotIsNull(): void
    {
        $after = Snapshot::capture(new Order(status: 'pending'));

        $changeSet = ChangeSet::diff(null, $after);

        $change = $changeSet->get('status');
        self::assertNotNull($change);
        self::assertSame(ChangeAction::Added, $change->action);
        self::assertNull($change->original);
        self::assertSame('pending', $change->modified);
    }

    public function testRemovedKeyWhenAfterSnapshotIsNull(): void
    {
        $before = Snapshot::capture(new Order(status: 'pending'));

        $changeSet = ChangeSet::diff($before, null);

        $change = $changeSet->get('status');
        self::assertNotNull($change);
        self::assertSame(ChangeAction::Removed, $change->action);
        self::assertSame('pending', $change->original);
        self::assertNull($change->modified);
    }

    public function testAddedNestedValueExprRelation(): void
    {
        $before = Snapshot::capture(new Order(status: 'pending', customer: null));
        $after = Snapshot::capture(new Order(status: 'pending', customer: new Customer('a@example.com')));

        $changeSet = ChangeSet::diff($before, $after);

        $change = $changeSet->get('customer');
        self::assertNotNull($change);
        self::assertSame(ChangeAction::Modified, $change->action);
        self::assertNull($change->original);
        self::assertSame('a@example.com', $change->modified);
    }

    public function testNestedEntityProducesNestedChange(): void
    {
        // customer uses valueExpr so it never nests; use a plain nested entity via lines instead.
        $before = Snapshot::capture(new Order(status: 'pending', lines: [new OrderLine('a', 1)]));
        $after = Snapshot::capture(new Order(status: 'pending', lines: [new OrderLine('a', 2)]));

        $changeSet = ChangeSet::diff($before, $after);

        $change = $changeSet->get('lines');
        self::assertNotNull($change);
        self::assertSame(ChangeAction::Nested, $change->action);
        self::assertInstanceOf(ChangeSet::class, $change->nested);

        $lineChange = $change->nested->get('a');
        self::assertNotNull($lineChange);
        self::assertSame(ChangeAction::Nested, $lineChange->action);
        self::assertInstanceOf(ChangeSet::class, $lineChange->nested);

        $quantityChange = $lineChange->nested->get('quantity');
        self::assertNotNull($quantityChange);
        self::assertSame(ChangeAction::Modified, $quantityChange->action);
        self::assertSame(1, $quantityChange->original);
        self::assertSame(2, $quantityChange->modified);
    }

    public function testCollectionItemAdded(): void
    {
        $before = Snapshot::capture(new Order(status: 'pending', lines: []));
        $after = Snapshot::capture(new Order(status: 'pending', lines: [new OrderLine('a', 1)]));

        $changeSet = ChangeSet::diff($before, $after);

        $linesChange = $changeSet->get('lines');
        self::assertNotNull($linesChange);
        self::assertInstanceOf(ChangeSet::class, $linesChange->nested);

        $lineChange = $linesChange->nested->get('a');
        self::assertNotNull($lineChange);
        self::assertSame(ChangeAction::Added, $lineChange->action);
        self::assertSame(['quantity' => 1], $lineChange->modified);
    }

    public function testCollectionItemWithUninitializedIdIsAddedRatherThanThrowing(): void
    {
        // Regression: a brand-new element with no identity yet (e.g. a
        // Doctrine entity added to a collection before flush()) used to
        // make AuditedCollection::getKeyValue() throw. It must instead be
        // diffed as an Added entry, keyed synthetically.
        $before = Snapshot::capture(new LineHolder(id: 1, lines: []));
        $after  = Snapshot::capture(new LineHolder(id: 1, lines: [new NullableKeyItem(id: null, quantity: 3)]));

        $changeSet = ChangeSet::diff($before, $after);

        $linesChange = $changeSet->get('lines');
        self::assertNotNull($linesChange);
        self::assertInstanceOf(ChangeSet::class, $linesChange->nested);

        $entries = $linesChange->nested->all();
        self::assertCount(1, $entries);

        $key = array_key_first($entries);
        self::assertIsString($key);
        self::assertStringStartsWith("\0new:", $key);

        $itemChange = $entries[$key];
        self::assertSame(ChangeAction::Added, $itemChange->action);
        self::assertSame(['quantity' => 3], $itemChange->modified);
    }

    public function testCollectionItemRemoved(): void
    {
        $before = Snapshot::capture(new Order(status: 'pending', lines: [new OrderLine('a', 1)]));
        $after = Snapshot::capture(new Order(status: 'pending', lines: []));

        $changeSet = ChangeSet::diff($before, $after);

        $linesChange = $changeSet->get('lines');
        self::assertNotNull($linesChange);
        self::assertInstanceOf(ChangeSet::class, $linesChange->nested);

        $lineChange = $linesChange->nested->get('a');
        self::assertNotNull($lineChange);
        self::assertSame(ChangeAction::Removed, $lineChange->action);
        self::assertSame(['quantity' => 1], $lineChange->original);
    }

    public function testUnchangedCollectionItemProducesNoChange(): void
    {
        $before = Snapshot::capture(new Order(status: 'pending', lines: [new OrderLine('a', 1)]));
        $after = Snapshot::capture(new Order(status: 'pending', lines: [new OrderLine('a', 1)]));

        $changeSet = ChangeSet::diff($before, $after);

        self::assertTrue($changeSet->isEmpty());
    }

    public function testMaxDepthStopsRecursionIntoNestedChanges(): void
    {
        $before = Snapshot::capture(new Order(status: 'pending', lines: [new OrderLine('a', 1)]));
        $after = Snapshot::capture(new Order(status: 'pending', lines: [new OrderLine('a', 2)]));

        // depth 0 = top-level Order diff itself; maxDepth=0 stops before even that.
        $changeSet = ChangeSet::diff($before, $after, maxDepth: 0);

        self::assertTrue($changeSet->isEmpty());
    }

    public function testMaxDepthOfOneStopsBeforeNestingIntoCollectionItems(): void
    {
        $before = Snapshot::capture(new Order(status: 'pending', lines: [new OrderLine('a', 1)]));
        $after = Snapshot::capture(new Order(status: 'pending', lines: [new OrderLine('a', 2)]));

        $changeSet = ChangeSet::diff($before, $after, maxDepth: 1);

        // top-level diff runs at depth 0, so 'lines' key is still evaluated,
        // but diffCollection is invoked at depth 1 which immediately hits the cap.
        $change = $changeSet->get('lines');
        self::assertNull($change);
    }
}
