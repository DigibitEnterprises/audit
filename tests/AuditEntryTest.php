<?php declare(strict_types=1);

namespace Digibit\Audit\Tests;

use Digibit\Audit\AuditEntry;
use Digibit\Audit\Diff\ChangeSet;
use Digibit\Audit\Snapshot\Snapshot;
use Digibit\Audit\Tests\Fixtures\Order;
use Digibit\Audit\Tests\Fixtures\OrderLine;
use PHPUnit\Framework\TestCase;

final class AuditEntryTest extends TestCase
{
    public function test_event_type_for_scalar_property_change(): void
    {
        $before = Snapshot::capture(new Order(status: 'pending'));
        $after  = Snapshot::capture(new Order(status: 'shipped'));

        $entries = AuditEntry::fromChangeSet(
            new Order(status: 'shipped'),
            ChangeSet::diff($before, $after),
            transactionId: 'txn-1',
        );

        self::assertCount(1, $entries);
        self::assertSame('order.status.modified', $entries[0]->eventType());
    }

    public function test_event_type_for_collection_element_added(): void
    {
        $before = Snapshot::capture(new Order(status: 'pending', lines: []));
        $after  = Snapshot::capture(new Order(status: 'pending', lines: [new OrderLine('a', 1)]));

        $entries = AuditEntry::fromChangeSet(
            new Order(status: 'pending', lines: [new OrderLine('a', 1)]),
            ChangeSet::diff($before, $after),
            transactionId: 'txn-1',
        );

        self::assertCount(1, $entries);
        // derived from the collection *property* name ("lines"), not the
        // per-element path ("lines[id:a]")
        self::assertSame('order.lines.added', $entries[0]->eventType());
    }

    public function test_event_type_for_collection_element_removed(): void
    {
        $before = Snapshot::capture(new Order(status: 'pending', lines: [new OrderLine('a', 1)]));
        $after  = Snapshot::capture(new Order(status: 'pending', lines: []));

        $entries = AuditEntry::fromChangeSet(
            new Order(status: 'pending', lines: []),
            ChangeSet::diff($before, $after),
            transactionId: 'txn-1',
        );

        self::assertCount(1, $entries);
        self::assertSame('order.lines.removed', $entries[0]->eventType());
    }
}
