<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Attributes;

use Digibit\Audit\Attributes\AuditedCollection;
use Digibit\Audit\Exception\InvalidCollectionKeyException;
use Digibit\Audit\Exception\PropertyNotFoundException;
use Digibit\Audit\Tests\Fixtures\BadUnionKeyItem;
use Digibit\Audit\Tests\Fixtures\NullableKeyItem;
use Digibit\Audit\Tests\Fixtures\OrderLine;
use Digibit\Audit\Tests\Fixtures\UninitializedKeyItem;
use Digibit\Audit\Tests\Fixtures\UntypedKeyItem;
use PHPUnit\Framework\TestCase;

final class AuditedCollectionTest extends TestCase
{
    public function testAcceptsStringTypedKey(): void
    {
        $config = new AuditedCollection(ofType: OrderLine::class, keyBy: 'id');

        $line = new OrderLine(id: 'line-1', quantity: 2);

        self::assertSame('line-1', $config->getKeyValue($line));
    }

    public function testRejectsUntypedKeyProperty(): void
    {
        $this->expectException(InvalidCollectionKeyException::class);
        $this->expectExceptionMessage("key 'id'");

        new AuditedCollection(ofType: UntypedKeyItem::class, keyBy: 'id');
    }

    public function testRejectsUnionTypeWithNonStringIntMember(): void
    {
        $this->expectException(InvalidCollectionKeyException::class);

        new AuditedCollection(ofType: BadUnionKeyItem::class, keyBy: 'id');
    }

    public function testRejectsUnknownKeyProperty(): void
    {
        $this->expectException(PropertyNotFoundException::class);

        new AuditedCollection(ofType: OrderLine::class, keyBy: 'doesNotExist');
    }

    public function testGetKeyValueSynthesizesAKeyWhenKeyPropertyIsUninitialized(): void
    {
        $config = new AuditedCollection(ofType: UninitializedKeyItem::class, keyBy: 'id');

        $key = $config->getKeyValue(new UninitializedKeyItem());

        self::assertIsString($key);
        self::assertStringStartsWith("\0new:", $key);
    }

    public function testGetKeyValueSynthesizesAKeyWhenKeyPropertyIsNull(): void
    {
        $config = new AuditedCollection(ofType: NullableKeyItem::class, keyBy: 'id');

        $key = $config->getKeyValue(new NullableKeyItem(id: null));

        self::assertIsString($key);
        self::assertStringStartsWith("\0new:", $key);
    }

    public function testGetKeyValueSynthesizesDistinctKeysForDistinctUnkeyedElements(): void
    {
        $config = new AuditedCollection(ofType: NullableKeyItem::class, keyBy: 'id');

        // Both elements must stay alive for the duration of the comparison —
        // spl_object_id() may be reused once an object is garbage collected,
        // which is fine in real usage (collection elements stay referenced
        // for the life of the snapshot) but would make an inline-throwaway
        // version of this test flaky.
        $itemA = new NullableKeyItem(id: null);
        $itemB = new NullableKeyItem(id: null);

        $a = $config->getKeyValue($itemA);
        $b = $config->getKeyValue($itemB);

        self::assertNotSame($a, $b);
    }

    public function testGetKeyValueResolvesOnceElementHasBeenAssignedAKey(): void
    {
        $config = new AuditedCollection(ofType: NullableKeyItem::class, keyBy: 'id');

        self::assertSame('line-9', $config->getKeyValue(new NullableKeyItem(id: 'line-9')));
    }
}
