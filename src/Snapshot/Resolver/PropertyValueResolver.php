<?php declare(strict_types=1);

namespace Digibit\Audit\Snapshot\Resolver;

use Digibit\Audit\Exception\ExpressionException;
use Digibit\Audit\Exception\NonObjectPropertyException;
use Digibit\Audit\Exception\UncapturableValueException;
use Digibit\Audit\Exception\UnresolvedValueException;
use Digibit\Audit\Support\PropertyValueReader;
use Digibit\Resolver\DefaultResolver;
use Digibit\Resolver\ResolverDispatcher;

final class PropertyValueResolver
{
    private static ?ResolverDispatcher $dispatcher = null;

    /**
     * Set only for the duration of one dispatch, so the shared fallback
     * closure in dispatcher() can phrase its Traversable/JsonSerializable
     * messages correctly depending on whether the value came from a
     * valueExpr — without changing the external ResolverDispatcher /
     * DefaultResolver call signature to carry that context explicitly.
     */
    private static ?string $currentValueExpr = null;

    public static function register(string $resolverClass, int $priority = 0): void
    {
        self::dispatcher()->register($resolverClass, $priority);
    }

    public static function registerFactory(string $producedClassName, \Closure $factory, int $priority = 0): void
    {
        self::dispatcher()->registerFactory($producedClassName, $factory, $priority);
    }

    /** Drops all registrations, including the built-ins, so the next resolve() rebuilds from scratch. */
    public static function reset(): void
    {
        self::$dispatcher = null;
    }

    public static function resolve(mixed $value, ?string $valueExpr = null): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($valueExpr === null) {
            self::assertAuditable($value, null);
            return self::dispatchWithContext($value, null);
        }

        if (!is_object($value)) {
            throw new NonObjectPropertyException(sprintf(
                'valueExpr "%s" configured for non-object value of type %s',
                $valueExpr,
                get_debug_type($value),
            ));
        }

        try {
            $extracted = PropertyValueReader::read($value, $valueExpr);
        } catch (\Throwable $e) {
            throw new ExpressionException(sprintf(
                'Failed to evaluate valueExpr "%s" on %s',
                $valueExpr,
                get_debug_type($value),
            ), previous: $e);
        }

        if ($extracted === null) {
            return null;
        }

        self::assertAuditable($extracted, $valueExpr);
        return self::dispatchWithContext($extracted, $valueExpr);
    }

    private static function assertAuditable(mixed $value, ?string $valueExpr): void
    {
        if ($value instanceof \Closure) {
            throw new UncapturableValueException($valueExpr !== null
                ? sprintf('valueExpr "%s" evaluated to a Closure.', $valueExpr)
                : 'Closures are not auditable.');
        }

        if (is_resource($value)) {
            throw new UncapturableValueException($valueExpr !== null
                ? sprintf('valueExpr "%s" evaluated to %s.', $valueExpr, get_debug_type($value))
                : sprintf('%s is not auditable.', get_debug_type($value)));
        }
    }

    private static function dispatchWithContext(mixed $value, ?string $valueExpr = null): mixed
    {
        $previous = self::$currentValueExpr;
        self::$currentValueExpr = $valueExpr;
        try {
            return self::dispatcher()->resolve($value);
        } finally {
            self::$currentValueExpr = $previous;
        }
    }

    private static function dispatcher(): ResolverDispatcher
    {
        return self::$dispatcher ??= (new ResolverDispatcher())
            ->register(EnumResolver::class, priority: 20)
            ->register(DateTimeResolver::class, priority: 20)
            ->register(DateIntervalResolver::class, priority: 20)
            ->register(AuditedEntityResolver::class, priority: 10)
            ->registerFactory(
                DefaultResolver::class,
                static fn() => new DefaultResolver(static function (mixed $subject) {
                    $valueExpr = self::$currentValueExpr;

                    return match (true) {
                        $subject instanceof \Closure => throw new UncapturableValueException(
                            'Closures are not auditable.'
                        ),
                        is_resource($subject) => throw new UncapturableValueException(
                            get_debug_type($subject) . ' is not auditable.'
                        ),
                        is_object($subject) && $subject instanceof \Traversable => throw new UnresolvedValueException(
                            $valueExpr !== null
                                ? sprintf(
                                    'valueExpr "%s" evaluated to %s, which is traversable.',
                                    $valueExpr,
                                    get_debug_type($subject),
                                )
                                : get_debug_type($subject) . ' is traversable.'
                        ),
                        is_object($subject) && $subject instanceof \JsonSerializable => throw new UnresolvedValueException(
                            $valueExpr !== null
                                ? sprintf(
                                    'valueExpr "%s" evaluated to %s, which has no registered resolver.',
                                    $valueExpr,
                                    get_debug_type($subject),
                                )
                                : get_debug_type($subject) . ' has no registered resolver.'
                        ),
                        is_object($subject) => throw new UnresolvedValueException(
                            'No value resolver registered for ' . get_debug_type($subject)
                        ),
                        default => $subject,
                    };
                }),
                priority: PHP_INT_MIN,
            );
    }
}
