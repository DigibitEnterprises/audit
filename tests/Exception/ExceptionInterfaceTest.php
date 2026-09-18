<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Exception;

use Digibit\Audit\Exception\CircularReferenceException;
use Digibit\Audit\Exception\ExceptionInterface;
use Digibit\Audit\Exception\ExpressionException;
use Digibit\Audit\Exception\InvalidCollectionKeyException;
use Digibit\Audit\Exception\InvalidIdentityTypeException;
use Digibit\Audit\Exception\NonObjectPropertyException;
use Digibit\Audit\Exception\NotAuditedException;
use Digibit\Audit\Exception\NullCollectionElementException;
use Digibit\Audit\Exception\PropertyNotFoundException;
use Digibit\Audit\Exception\UncapturableValueException;
use Digibit\Audit\Exception\UninitializedIdentityException;
use Digibit\Audit\Exception\UnresolvedValueException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExceptionInterfaceTest extends TestCase
{
    #[DataProvider('concreteExceptionClasses')]
    public function testEveryConcreteExceptionImplementsExceptionInterface(string $class): void
    {
        self::assertContains(ExceptionInterface::class, class_implements($class));
    }

    public static function concreteExceptionClasses(): array
    {
        return [
            [CircularReferenceException::class],
            [ExpressionException::class],
            [InvalidCollectionKeyException::class],
            [InvalidIdentityTypeException::class],
            [NonObjectPropertyException::class],
            [NotAuditedException::class],
            [NullCollectionElementException::class],
            [PropertyNotFoundException::class],
            [UncapturableValueException::class],
            [UninitializedIdentityException::class],
            [UnresolvedValueException::class],
        ];
    }
}
