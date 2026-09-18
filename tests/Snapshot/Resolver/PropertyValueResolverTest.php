<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Snapshot\Resolver;

use Digibit\Audit\Exception\ExpressionException;
use Digibit\Audit\Exception\NonObjectPropertyException;
use Digibit\Audit\Exception\UncapturableValueException;
use Digibit\Audit\Exception\UnresolvedValueException;
use Digibit\Audit\Snapshot\Resolver\PropertyValueResolver;
use Digibit\Audit\Snapshot\Snapshot;
use Digibit\Audit\Tests\Fixtures\ContainerStatus;
use Digibit\Audit\Tests\Fixtures\Customer;
use Digibit\Audit\Tests\Fixtures\Direction;
use Digibit\Audit\Tests\Fixtures\JsonSerializableValue;
use Digibit\Audit\Tests\Fixtures\NotAudited;
use PHPUnit\Framework\TestCase;

final class PropertyValueResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        // the dispatcher is a process-wide static singleton; drop any
        // registrations so tests don't leak into one another
        PropertyValueResolver::reset();
    }

    public function testNullPassesThroughWithoutInvokingTheDispatcher(): void
    {
        self::assertNull(PropertyValueResolver::resolve(null, null));
    }

    public function testResolvesBackedEnumToItsValue(): void
    {
        $result = PropertyValueResolver::resolve(ContainerStatus::Active, null);

        self::assertSame('active', $result);
    }

    public function testResolvesUnitEnumToItsName(): void
    {
        $result = PropertyValueResolver::resolve(Direction::Inbound, null);

        self::assertSame('Inbound', $result);
    }

    public function testResolvesDateTimeInterfaceToAtomFormat(): void
    {
        $date = new \DateTimeImmutable('2026-01-15T10:30:00+00:00');

        $result = PropertyValueResolver::resolve($date, null);

        self::assertSame($date->format(\DateTimeInterface::ATOM), $result);
    }

    public function testResolvesDateIntervalToIsoDuration(): void
    {
        $interval = new \DateInterval('P1Y2M3D');

        $result = PropertyValueResolver::resolve($interval, null);

        self::assertSame('+P1Y2M3DT0H0M0S', $result);
    }

    public function testResolvesNestedAuditedEntityToASnapshot(): void
    {
        $customer = new Customer('a@example.com');

        $result = PropertyValueResolver::resolve($customer, null);

        self::assertInstanceOf(Snapshot::class, $result);
        self::assertSame('a@example.com', $result->entries()['email']);
    }

    public function testValueExprExtractsAndRecursivelyResolvesAScalar(): void
    {
        $customer = new Customer('a@example.com');

        $result = PropertyValueResolver::resolve($customer, 'email');

        self::assertSame('a@example.com', $result);
    }

    public function testValueExprReturningNullShortCircuitsToNull(): void
    {
        $result = PropertyValueResolver::resolve(
            new class { public ?string $email = null; },
            'email',
        );

        self::assertNull($result);
    }

    public function testValueExprOnNonObjectValueThrows(): void
    {
        $this->expectException(NonObjectPropertyException::class);
        $this->expectExceptionMessage('configured for non-object value');

        PropertyValueResolver::resolve('a scalar', 'email');
    }

    public function testValueExprEvaluationFailureIsWrapped(): void
    {
        $this->expectException(ExpressionException::class);
        $this->expectExceptionMessage('Failed to evaluate valueExpr');

        PropertyValueResolver::resolve(new Customer('a@example.com'), 'doesNotExist');
    }

    public function testValueExprExtractingAClosureThrowsUncapturable(): void
    {
        $this->expectException(UncapturableValueException::class);
        $this->expectExceptionMessage('valueExpr "callback" evaluated to a Closure');

        PropertyValueResolver::resolve(
            new class { public \Closure $callback; public function __construct() { $this->callback = fn() => 1; } },
            'callback',
        );
    }

    public function testValueExprExtractingATraversableThrowsUnresolved(): void
    {
        $this->expectException(UnresolvedValueException::class);
        $this->expectExceptionMessageMatches('/valueExpr "items" evaluated to.*which is traversable/');

        PropertyValueResolver::resolve(
            new class { public \ArrayIterator $items; public function __construct() { $this->items = new \ArrayIterator([1, 2]); } },
            'items',
        );
    }

    public function testClosureValueThrows(): void
    {
        $this->expectException(UncapturableValueException::class);
        $this->expectExceptionMessage('Closures are not auditable');

        PropertyValueResolver::resolve(fn() => 1, null);
    }

    public function testTraversableValueThrows(): void
    {
        $this->expectException(UnresolvedValueException::class);
        $this->expectExceptionMessage('is traversable');

        PropertyValueResolver::resolve(new \ArrayIterator([1, 2]), null);
    }

    public function testJsonSerializableValueThrows(): void
    {
        $this->expectException(UnresolvedValueException::class);
        $this->expectExceptionMessage('has no registered resolver');

        PropertyValueResolver::resolve(new JsonSerializableValue(), null);
    }

    public function testUnrecognizedObjectValueThrows(): void
    {
        $this->expectException(UnresolvedValueException::class);
        $this->expectExceptionMessage('No value resolver registered for');

        PropertyValueResolver::resolve(new NotAudited(), null);
    }

    public function testRegisterAddsACustomResolverForOtherwiseUnrecognizedObjects(): void
    {
        $resolver = new class implements \Digibit\Resolver\ResolverInterface {
            public static function supports(mixed $subject): bool
            {
                return $subject instanceof NotAudited;
            }

            public function resolve(mixed $subject): mixed
            {
                return 'custom:' . $subject->name;
            }
        };
        PropertyValueResolver::registerFactory($resolver::class, static fn() => $resolver);

        $result = PropertyValueResolver::resolve(new NotAudited(), null);

        self::assertSame('custom:nope', $result);
    }

    public function testResetDropsCustomRegistrationsAndRestoresBuiltins(): void
    {
        $resolver = new class implements \Digibit\Resolver\ResolverInterface {
            public static function supports(mixed $subject): bool
            {
                return $subject instanceof NotAudited;
            }

            public function resolve(mixed $subject): mixed
            {
                return 'custom';
            }
        };
        PropertyValueResolver::registerFactory($resolver::class, static fn() => $resolver);
        PropertyValueResolver::reset();

        // built-ins (e.g. enum resolution) still work after reset
        self::assertSame('active', PropertyValueResolver::resolve(ContainerStatus::Active, null));

        // the custom registration is gone — falls back to the throwing default
        $this->expectException(UnresolvedValueException::class);
        PropertyValueResolver::resolve(new NotAudited(), null);
    }
}
