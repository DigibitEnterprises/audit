<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Attributes;

use Digibit\Audit\Attributes\Audited;
use Digibit\Audit\Exception\InvalidIdentityTypeException;
use Digibit\Audit\Exception\NotAuditedException;
use Digibit\Audit\Exception\UninitializedIdentityException;
use Digibit\Audit\Tests\Fixtures\Container;
use Digibit\Audit\Tests\Fixtures\Address;
use Digibit\Audit\Tests\Fixtures\NotAudited;
use Digibit\Audit\Tests\Fixtures\NullableIdEntity;
use Digibit\Audit\Tests\Fixtures\Product;
use Digibit\Audit\Tests\Fixtures\UninitializedIdEntity;
use PHPUnit\Framework\TestCase;

final class AuditedTest extends TestCase
{
    public function testResolvesIdFromDefaultIdProperty(): void
    {
        $container = new Container(101, address: new Address(102, 'Boise'));

        self::assertSame(101, Audited::idFor($container));
    }

    public function testResolvesIdFromCustomIdProperty(): void
    {
        $product = new Product(sku: 'sku-123', name: 'Widget');

        self::assertSame('sku-123', Audited::idFor($product));
    }

    public function testThrowsWhenEntityIsNotAudited(): void
    {
        $this->expectException(NotAuditedException::class);
        $this->expectExceptionMessage('is not a #[Audited]');

        Audited::idFor(new NotAudited());
    }

    public function testThrowsWhenIdPropertyIsUninitialized(): void
    {
        $this->expectException(UninitializedIdentityException::class);
        $this->expectExceptionMessage('is uninitialized');

        Audited::idFor(new UninitializedIdEntity());
    }

    public function testThrowsWhenIdPropertyIsNull(): void
    {
        $this->expectException(InvalidIdentityTypeException::class);
        $this->expectExceptionMessage('must resolve to string or int, got null');

        Audited::idFor(new NullableIdEntity(id: null));
    }

    public function testResolvesOnceEntityHasBeenAssignedAnId(): void
    {
        $entity = new NullableIdEntity(id: 42);

        self::assertSame(42, Audited::idFor($entity));
    }
}
