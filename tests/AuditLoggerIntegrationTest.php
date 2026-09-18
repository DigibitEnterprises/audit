<?php declare(strict_types=1);

namespace Digibit\Audit\Tests;

use Digibit\Audit\Diff\ChangeSet;
use Digibit\Audit\Snapshot\Snapshot;
use Digibit\Audit\Tests\Fixtures\Address;
use Digibit\Audit\Tests\Fixtures\AuditLog;
use Digibit\Audit\Tests\Fixtures\AuditLogger;
use Digibit\Audit\Tests\Fixtures\Container;
use Digibit\Audit\Tests\Fixtures\OrderLine;
use Digibit\Audit\Tests\Fixtures\Product;
use Digibit\Audit\Tests\Fixtures\StorageCollection;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end exercise of Snapshot -> ChangeSet::diff -> AuditLogger, standing
 * in for how a consuming project would wire the library up. Covers two paths
 * that weren't hit by SnapshotTest/ChangeSetTest in isolation:
 *  - a plain #[AuditedProperty] object (no valueExpr) recursing via
 *    AuditedEntityResolver (Address nested under Container)
 *  - AuditLogger turning a nested ChangeSet into a parent/child AuditLog tree
 */
final class AuditLoggerIntegrationTest extends TestCase
{
    private StorageCollection $storage;
    private AuditLogger $logger;

    protected function setUp(): void
    {
        $this->storage = new StorageCollection();
        $this->logger  = new AuditLogger($this->storage);
    }

    public function test_nested_entity_property_change_produces_a_nested_change(): void
    {
        $before = new Container(101, address: new Address(102, 'Boise'), lines: [new OrderLine('103', 1)]);
        $after  = new Container(101, address: new Address(102, 'Denver'), lines: [new OrderLine('103', 1)]);

        $diff = ChangeSet::diff(Snapshot::capture($before), Snapshot::capture($after));

        self::assertFalse($diff->isEmpty());

        $addressChange = $diff->get('address');
        self::assertNotNull($addressChange);
        self::assertTrue($addressChange->action->value === 'nested');

        $cityChange = $addressChange->nested->get('city');
        self::assertNotNull($cityChange);
        self::assertSame('Boise', $cityChange->original);
        self::assertSame('Denver', $cityChange->modified);

        // lines were untouched — no change entry should be emitted for them
        self::assertNull($diff->get('lines'));
    }

    public function test_collection_element_property_change_produces_a_keyed_nested_change(): void
    {
        $before = new Container(104, address: new Address(105, 'Boise'), lines: [new OrderLine('106', 1)]);
        $after  = new Container(104, address: new Address(105, 'Boise'), lines: [new OrderLine('106', 2)]);

        $diff = ChangeSet::diff(Snapshot::capture($before), Snapshot::capture($after));

        self::assertFalse($diff->isEmpty());
        self::assertNull($diff->get('address')); // address untouched

        $linesChange = $diff->get('lines');
        self::assertNotNull($linesChange);

        $lineChange = $linesChange->nested->get('106');
        self::assertNotNull($lineChange);

        $quantityChange = $lineChange->nested->get('quantity');
        self::assertSame(1, $quantityChange->original);
        self::assertSame(2, $quantityChange->modified);
    }

    public function test_audit_logger_flattens_a_nested_changeset_into_a_parent_child_log_tree(): void
    {
        $before = new Container(104, address: new Address(105, 'Boise'), lines: [new OrderLine('106', 1)]);
        $after  = new Container(104, address: new Address(105, 'Boise'), lines: [new OrderLine('106', 2)]);

        $diff = ChangeSet::diff(Snapshot::capture($before), Snapshot::capture($after));
        $logs = $this->logger->logFieldChanges($before, $diff);

        // root "nested" row for `lines`, child "nested" row for the `[id:106]` element,
        // grandchild "field_change" row for `quantity`
        self::assertCount(3, $logs);
        self::assertCount(3, $this->storage->all());

        $root = $logs[0];
        self::assertTrue($root->isNested());
        self::assertCount(1, $root->getChildren());

        $element = $root->getChildren()[0];
        self::assertTrue($element->isNested());
        self::assertCount(1, $element->getChildren());
        self::assertSame($root, $element->getParent());

        $child = $element->getChildren()[0];
        self::assertTrue($child->isFieldChange());
        self::assertSame('1', $child->getOldValue());
        self::assertSame('2', $child->getNewValue());
        self::assertSame($element, $child->getParent());
    }

    public function test_status_change_factory_produces_a_readable_log_row(): void
    {
        $container = new Container(107, address: new Address(108, 'Boise'));

        $log = AuditLog::statusChange($container, 'active', 'inactive', notes: 'manual deactivation');

        self::assertTrue($log->isStatusChange());
        self::assertSame('active', $log->getOldValue());
        self::assertSame('inactive', $log->getNewValue());
        self::assertSame('manual deactivation', $log->getNotes());
        self::assertSame($container->getId(), $log->getEntityId());
    }

    public function test_entity_id_resolves_via_the_entitys_own_diffable_entity_id_property(): void
    {
        $before = new Product(sku: 'sku-123', name: 'Widget');
        $after  = new Product(sku: 'sku-123', name: 'Widget XL');

        $diff = ChangeSet::diff(Snapshot::capture($before), Snapshot::capture($after));
        $logs = $this->logger->logFieldChanges($before, $diff);

        self::assertCount(1, $logs);
        self::assertSame('sku-123', $logs[0]->getEntityId()); // resolved via #[Audited(id: 'sku')], not a getId() call
    }
}
